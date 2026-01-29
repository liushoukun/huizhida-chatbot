<?php

namespace HuiZhiDa\Engine\Channel\Infrastructure\Adapters;

use EasyWeChat\Work\Application;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\EventContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\FileContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\ImageContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\MarkdownContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\TextContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\VideoContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\VoiceContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationOutputQueue;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use HuiZhiDa\Core\Domain\Conversation\Enums\EventType;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use HuiZhiDa\Engine\Channel\Domain\Contracts\ChannelAdapterInterface;
use HuiZhiDa\Engine\Channel\Domain\DTO\CallbackPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RedJasmine\Support\Domain\Data\UserData;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class WorkWechatAdapter implements ChannelAdapterInterface
{
    protected array $config;

    protected Application $workWechatApp;

    public function __construct(array $config = [])
    {

        $this->config = $config;

        $this->workWechatApp = new Application($config);
    }

    public function health(Request $request)
    {

        $server = $this->workWechatApp->getServer();

        return $server->serve();
    }

    public function verifySignature(Request $request) : bool
    {
        // TODO: 实现企业微信签名验证
        $token          = $this->config['token'] ?? '';
        $encodingAesKey = $this->config['aes_key'] ?? '';

        // 简化实现，实际需要根据企业微信文档实现
        return true;
    }

    /**
     * 从回调请求中提取最小载荷（仅解密 + 提取），不做 sync_msg、不下载。
     */
    public function extractCallbackPayload(Request $request, string $channelId) : ?CallbackPayload
    {
        Log::debug('workWechat extractCallbackPayload', [
            'body'   => $request->getContent(),
            'params' => $request->query()
        ]);
        $server = $this->workWechatApp->getServer();
        $message = $server->getDecryptedMessage();

        if ($message->Event !== 'kf_msg_or_event') {
            throw new Exception('Invalid message event');
        }


        return CallbackPayload::from([
            'channelId' => $channelId,
            'payload'   => [
                'token'      => $message->Token,
                'open_kf_id' => $message->OpenKfId,
            ],
        ]);
    }

    /**
     * 根据 CallbackPayload 拉取并解析消息（含 sync_msg、下载媒体等），仅 Worker 调用。
     *
     * @param  CallbackPayload  $payload
     *
     * @return ChannelMessage[]
     * @throws Exception
     */
    public function fetchAndParseMessages(CallbackPayload $payload) : array
    {
        $token    = $payload->payload['token'] ?? '';
        $openKfId = $payload->payload['open_kf_id'] ?? '';
        if ($token === '' || $openKfId === '') {
            Log::warning('WorkWechat fetchAndParseMessages: missing token or open_kf_id', [
                'payload' => $payload->payload,
            ]);
            return [];
        }
        return $this->doSyncAndParseMessages($token, $openKfId);
    }

    /**
     * 解析渠道消息格式（同步路径），转换为统一格式。
     *
     * @param  Request  $request
     *
     * @return array|ChannelMessage[]
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function parseMessages(Request $request) : array
    {
        Log::debug('workWechat parseMessages', [
            'body'   => $request->getContent(),
            'params' => $request->query()
        ]);
        $server  = $this->workWechatApp->getServer();
        $message = $server->getDecryptedMessage();

        if ($message->Event !== 'kf_msg_or_event') {
            throw new Exception('Invalid message event');
        }

        return $this->doSyncAndParseMessages($message->Token, $message->OpenKfId);
    }

    /**
     * 执行 sync_msg 并解析 msg_list 为 ChannelMessage[]（含 cursor、downloadMedia）。
     * 空 msg_list 时返回 []。
     *
     * @param  string  $token
     * @param  string  $openKfId
     *
     * @return ChannelMessage[]
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    protected function doSyncAndParseMessages(string $token, string $openKfId) : array
    {
        $api = $this->workWechatApp->getClient();

        $cacheKey = "workwechat:sync_cursor:{$openKfId}";
        $cursor   = Cache::get($cacheKey);

        $response = $api->postJson('/cgi-bin/kf/sync_msg', [
            'token'     => $token,
            'open_kfid' => $openKfId,
            'cursor'    => $cursor,
            'limit'     => 1000,
        ]);

        $responseData = json_decode($response->getContent(), true);
        Log::info('sync_msg response', $responseData);

        if (isset($responseData['errcode']) && $responseData['errcode'] !== 0) {
            throw new Exception('Sync message failed: '.($responseData['errmsg'] ?? 'Unknown error'));
        }

        if (isset($responseData['next_cursor']) && !empty($responseData['next_cursor'])) {
            Cache::put($cacheKey, $responseData['next_cursor'], now()->addDays(10));
        }

        $msgList = $responseData['msg_list'] ?? [];
        if (empty($msgList)) {
            return [];
        }

        $supportedMsgTypes = ['text', 'image', 'voice', 'video', 'file', 'event'];
        $messages          = [];
        foreach ($msgList as $msgData) {
            $msgType = $msgData['msgtype'] ?? 'text';

            if (!in_array($msgType, $supportedMsgTypes, true)) {
                Log::info('跳过不支持的消息类型', $msgData);
                continue;
            }
            // 过滤 接待人员的消息
            if (!in_array((int) $msgData['origin'], [3, 4], true)) {
                Log::info('跳过不支持的消息发送者', $msgData);
                continue;
            }


            $message    = $this->convertToChannelMessage($msgData, $openKfId);
            $messages[] = $message;
        }

        return $messages;
    }

    /**
     * 将企业微信消息转换为ChannelMessage
     *
     * @param  array  $msgData  企业微信消息数据
     * @param  string  $openKfId  客服ID
     *
     * @return ChannelMessage
     * @throws Exception
     */
    protected function convertToChannelMessage(array $msgData, string $openKfId) : ChannelMessage
    {
        $external_userid                = $msgData['external_userid'] ?? $msgData['event']['external_userid'];
        $message                        = new ChannelMessage();
        $message->messageId             = $message->getMessageId();
        $message->channelConversationId = $external_userid;// 企业微信是一个用户是一个会话,会话状态支持 轮换，结束后，可以重新接入
        $message->channelMessageId      = $msgData['msgid'] ?? '';
        $message->messageType           = $this->mapMessageType($msgData['msgtype'] ?? 'text');
        $message->contentType           = $this->mapContentType($msgData['msgtype'] ?? 'text');
        $message->timestamp             = $msgData['send_time'] ?? time();
        $message->rawData               = json_encode($msgData, JSON_UNESCAPED_UNICODE);
        $message->channelAppId          = $openKfId;


        // 解析发送者信息
        $message->sender = UserData::from([
            'type'     => 'user',
            'id'       => $external_userid,
            'nickname' => $msgData['nickname'] ?? '',
        ]);

        // 根据消息类型解析内容
        $content = $this->parseMessageContent($msgData, $message->contentType);
        if ($content) {
            // 将Content对象转换为数组
            $contentArray = $this->contentToArray($content);
            $message->setContentData($message->contentType, $contentArray);
        }

        return $message;
    }

    protected function mapMessageType(string $wecomType) : MessageType
    {
        $map = [
            'text'  => MessageType::Chat,
            'image' => MessageType::Chat,
            'voice' => MessageType::Chat,
            'video' => MessageType::Chat,
            'file'  => MessageType::Chat,
            'event' => MessageType::Event,
        ];

        return $map[$wecomType] ?? MessageType::Chat;
    }

    protected function mapContentType(string $wecomType) : ContentType
    {
        $map = [
            'text'  => ContentType::Text,
            'image' => ContentType::Image,
            'voice' => ContentType::Voice,
            'video' => ContentType::Video,
            'file'  => ContentType::File,
            'event' => ContentType::Event,
        ];

        return $map[$wecomType] ?? ContentType::Text;
    }

    /**
     * 解析消息内容
     *
     * @param  array  $msgData  消息数据
     * @param  ContentType  $contentType  内容类型
     *
     * @return EventContent|FileContent|ImageContent|TextContent|VideoContent|VoiceContent|null
     * @throws Exception
     */
    protected function parseMessageContent(array $msgData, ContentType $contentType) : TextContent|EventContent|FileContent|ImageContent|VoiceContent|VideoContent|null
    {
        $msgType = $msgData['msgtype'] ?? 'text';

        switch ($contentType) {
            case ContentType::Text:
                return $this->parseTextContent($msgData);

            case ContentType::Image:
                return $this->parseImageContent($msgData);

            case ContentType::Voice:
                return $this->parseVoiceContent($msgData);

            case ContentType::Video:
                return $this->parseVideoContent($msgData);

            case ContentType::File:
                return $this->parseFileContent($msgData);

            case ContentType::Event:
                return $this->parseEventContent($msgData);

            default:
                Log::warning('Unsupported message type', ['msgtype' => $msgType]);
                return null;
        }
    }

    /**
     * 解析文本消息
     */
    protected function parseTextContent(array $msgData) : TextContent
    {
        $content       = new TextContent();
        $content->text = $msgData['text']['content'] ?? '';

        return $content;
    }

    /**
     * 解析图片消息
     */
    protected function parseImageContent(array $msgData) : ImageContent
    {
        $content = new ImageContent();
        $mediaId = $msgData['image']['media_id'] ?? '';

        // 下载图片文件
        $filePath = $this->downloadMedia($mediaId, 'image');
        if ($filePath) {
            $content->url = Storage::disk('public')->url($filePath);
            // 设置媒体内容属性
            $content->disk = 'public';
            $content->path = $filePath;
            $content->type = 'image';
        }

        // 解析图片信息
        if (isset($msgData['image']['pic_width'])) {
            $content->width = (int) $msgData['image']['pic_width'];
        }
        if (isset($msgData['image']['pic_height'])) {
            $content->height = (int) $msgData['image']['pic_height'];
        }

        return $content;
    }

    /**
     * 下载企业微信媒体文件
     *
     * @param  string  $mediaId  媒体ID
     * @param  string  $type  媒体类型 (image, voice, video, file)
     *
     * @return string|null 保存的文件路径（相对于storage），失败返回null
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    public function downloadMedia(string $mediaId, string $type) : ?string
    {
        if (empty($mediaId)) {
            return null;
        }

        try {
            $api = $this->workWechatApp->getClient();

            // 调用企业微信客服API获取媒体文件
            // 根据文档：https://developer.work.weixin.qq.com/document/path/90254
            // 客服消息使用 /cgi-bin/media/get
            $response = $api->get('/cgi-bin/media/get', [
                'media_id' => $mediaId,
            ]);

            // 检查响应状态
            if ($response->getStatusCode() !== 200) {
                Log::error('Download media failed', [
                    'media_id' => $mediaId,
                    'status'   => $response->getStatusCode(),
                ]);
                return null;
            }

            // 检查响应内容，企业微信可能返回JSON错误
            $content     = $response->getContent();
            $contentType = $response->getHeader('Content-Type')[0] ?? '';

            // 如果返回的是JSON（错误响应），解析错误信息
            if (str_contains($contentType, 'application/json') || (str_starts_with($content, '{') || str_starts_with($content, '['))) {
                $errorData = json_decode($content, true);
                if (isset($errorData['errcode']) && $errorData['errcode'] !== 0) {
                    Log::error('Download media API error', [
                        'media_id' => $mediaId,
                        'error'    => $errorData['errmsg'] ?? 'Unknown error',
                        'errcode'  => $errorData['errcode'],
                    ]);
                    return null;
                }
            }

            // 获取Content-Type判断文件类型
            $extension = $this->getExtensionFromContentType($contentType, $type);

            // 生成文件路径
            $directory = date('Y/m/d')."/workwechat/media/{$type}";
            $filename  = Str::uuid().'.'.$extension;
            $filePath  = $directory.'/'.$filename;

            // 使用 public 磁盘存储媒体文件（需要生成公共 URL）
            $disk = Storage::disk('public');

            // 确保目录存在
            if (!$disk->exists($directory)) {
                $disk->makeDirectory($directory, 0755, true);
            }

            // 保存文件
            $disk->put($filePath, $content);

            Log::info('Media downloaded successfully', [
                'media_id' => $mediaId,
                'type'     => $type,
                'path'     => $filePath,
            ]);

            return $filePath;
        } catch (Exception $e) {
            Log::error('Download media exception', [
                'media_id' => $mediaId,
                'type'     => $type,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * 根据Content-Type获取文件扩展名
     */
    protected function getExtensionFromContentType(string $contentType, string $defaultType) : string
    {
        $map = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'audio/amr'       => 'amr',
            'audio/mpeg'      => 'mp3',
            'audio/mp3'       => 'mp3',
            'video/mp4'       => 'mp4',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
        ];

        if (isset($map[$contentType])) {
            return $map[$contentType];
        }

        // 默认扩展名
        $defaults = [
            'image' => 'jpg',
            'voice' => 'amr',
            'video' => 'mp4',
            'file'  => 'bin',
        ];

        return $defaults[$defaultType] ?? 'bin';
    }

    /**
     * 解析语音消息
     */
    protected function parseVoiceContent(array $msgData) : VoiceContent
    {
        $content = new VoiceContent();
        $mediaId = $msgData['voice']['media_id'] ?? '';

        // 下载语音文件
        $filePath = $this->downloadMedia($mediaId, 'voice');
        if ($filePath) {
            $content->url = Storage::disk('public')->url($filePath);
            // 设置媒体内容属性
            $content->disk = 'public';
            $content->path = $filePath;
            $content->type = 'voice';
        }

        return $content;
    }

    /**
     * 解析视频消息
     */
    protected function parseVideoContent(array $msgData) : VideoContent
    {
        $content = new VideoContent();
        $mediaId = $msgData['video']['media_id'] ?? '';

        // 下载视频文件
        $filePath = $this->downloadMedia($mediaId, 'video');
        if ($filePath) {
            $content->url = Storage::disk('public')->url($filePath);
            // 设置媒体内容属性
            $content->disk = 'public';
            $content->path = $filePath;
            $content->type = 'video';
        }

        // 下载视频缩略图
        $thumbMediaId = $msgData['video']['thumb_media_id'] ?? '';
        if ($thumbMediaId) {
            $thumbPath = $this->downloadMedia($thumbMediaId, 'image');
            if ($thumbPath) {
                $content->thumbUrl = Storage::disk('public')->url($thumbPath);
            }
        }

        return $content;
    }

    /**
     * 解析文件消息
     */
    protected function parseFileContent(array $msgData) : FileContent
    {
        $content = new FileContent();
        $mediaId = $msgData['file']['media_id'] ?? '';

        // 下载文件
        $filePath = $this->downloadMedia($mediaId, 'file');
        if ($filePath) {
            $content->url       = Storage::disk('public')->url($filePath);
            $content->filename  = basename($filePath);
            $content->extension = pathinfo($filePath, PATHINFO_EXTENSION);
            // 设置媒体内容属性
            $content->disk = 'public';
            $content->path = $filePath;
            $content->type = 'file';
        }

        return $content;
    }

    /**
     * 解析事件消息
     *
     * @param  array  $msgData  消息数据
     *
     * @return EventContent|null
     */
    protected function parseEventContent(array $msgData) : ?EventContent
    {
        $content   = new EventContent();
        $eventData = $msgData['event'] ?? [];
        // 获取事件类型，可能直接在顶层或 event 字段中
        $eventType = $eventData['event_type'];

        // 获取事件数据，可能在 event_data 字段中，也可能直接在顶层


        // 根据企业微信事件类型映射到 EventType 枚举
        if ($eventType === 'session_status_change') {
            // 会话状态变更事件
            $serviceState = (int) ($eventData['change_type'] ?? 0);


            // 1-从接待池接入会话 2-转接会话 3-结束会话 4-重新接入已结束/已转接会话
            switch ($serviceState) {
                case 2:
                case 4:
                case 1:
                    $content->event    = EventType::TransferToHuman;
                    $servicer          = $eventData['new_servicer_userid'];
                    $content->servicer = $servicer;
                    break;
                case 3: // 结束会话
                    $content->event = EventType::Closed;
                    break;
            }

            return $content;
        } else {


            // 其他事件类型，记录日志
            Log::warning('未知的事件类型', [
                'event'      => $eventType,
                'event_data' => $eventData,
                'msg_data'   => $msgData,
            ]);


        }
        return null;


    }

    /**
     * 将Content对象转换为数组
     */
    protected function contentToArray($content) : array
    {
        if (method_exists($content, 'toArray')) {
            return $content->toArray();
        }

        // 手动转换为数组
        $array = [];
        foreach (get_object_vars($content) as $key => $value) {
            $array[$key] = $value;
        }
        return $array;
    }

    public function convertToChannelFormat(ChannelMessage $message) : array
    {
        $data = [
            'msgtype' => $message->contentType === ContentType::Text ? 'text' : 'news',
        ];

        if ($message->contentType === ContentType::Text && $message->content instanceof TextContent) {
            $data['text'] = [
                'content' => $message->content->text,
            ];
        } else {
            // 处理其他类型的内容
            $data['news'] = [];
        }

        return $data;
    }

    public function sendMessages(ConversationOutputQueue $conversationAnswer) : void
    {
        $api = $this->workWechatApp->getClient();

        foreach ($conversationAnswer->messages as $message) {
            // 根据消息类型处理
            if ($message->messageType === MessageType::Chat) {
                // 处理聊天消息
                $this->sendChatMessage($api, $message, $conversationAnswer);
            } elseif ($message->messageType === MessageType::Event) {
                // 处理事件消息
                $this->handleEventMessage($message, $conversationAnswer);
            } else {
                Log::warning('未知的消息类型', [
                    'message_id'   => $message->getMessageId(),
                    'message_type' => $message->messageType->value ?? null,
                ]);
            }
        }
    }

    /**
     * 发送聊天消息
     */
    protected function sendChatMessage($api, ChannelMessage $message, ConversationOutputQueue $conversationAnswer) : void
    {
        $workWechatMessages = $this->convertToWorkWechatMessage($message);

        foreach ($workWechatMessages as $index => $workWechatMessage) {
            $workWechatMessage['touser']    = $conversationAnswer->user->getID();
            $workWechatMessage['open_kfid'] = $conversationAnswer->channelAppId;
            $workWechatMessage['msgid']     = $message->getMessageId()."-{$index}";
            Log::debug('发送消息到企业微信', $workWechatMessage);
            $response = $api->postJson(
                '/cgi-bin/kf/send_msg',
                $workWechatMessage,
            );
            Log::debug('发送结果', [
                'status' => $response->getStatusCode(),
                'body'   => json_decode($response->getContent(), true)
            ]);
        }
    }

    /**
     * 处理事件消息
     */
    protected function handleEventMessage(ChannelMessage $message, ConversationOutputQueue $conversationAnswer) : void
    {
        // 获取事件内容
        $content = $message->getContent();
        if (!$content instanceof EventContent) {
            Log::warning('事件消息内容类型不正确', [
                'message_id'   => $message->getMessageId(),
                'content_type' => get_class($content),
            ]);
            return;
        }

        // 判断事件类型
        $eventType = $content->event;
        Log::info('处理事件消息', [
            'message_id'      => $message->getMessageId(),
            'event_type'      => $eventType->value,
            'conversation_id' => $conversationAnswer->conversationId,
        ]);

        // 根据事件类型处理
        if ($eventType === EventType::TransferToHumanQueue) {
            // 转人工
            $this->transferToHumanQueuing($conversationAnswer);
        } elseif ($eventType === EventType::Closed) {
            // 关闭会话
            $this->closeConversation($conversationAnswer);
        } else {
            Log::warning('未知的事件类型', [
                'message_id' => $message->getMessageId(),
                'event_type' => $eventType->value,
            ]);
        }
    }

    /**
     * 关闭会话
     */
    protected function closeConversation(ConversationOutputQueue $conversationAnswer) : void
    {
        $api = $this->workWechatApp->getClient();
        // /cgi-bin/kf/service_state/trans
        // 文档: https://developer.work.weixin.qq.com/document/path/94669#%E5%8F%98%E6%9B%B4%E4%BC%9A%E8%AF%9D%E7%8A%B6%E6%80%81
        // service_state: 3 表示会话结束
        $data = [
            'open_kfid'       => $conversationAnswer->channelAppId,
            'external_userid' => $conversationAnswer->user->getID(),
            'service_state'   => 4, // 3 表示会话结束
        ];
        Log::info('关闭会话', $data);
        $response = $api->postJson('/cgi-bin/kf/service_state/trans', $data);
        Log::info('关闭会话返回', [
            'status'  => $response->getStatusCode(),
            'content' => $response->getContent(),
        ]);
    }

    /**
     * 转换为企业微信消息格式（可能返回多条消息）
     *
     * @return array 企业微信消息数组
     */
    protected function convertToWorkWechatMessage(ChannelMessage $channelMessage) : array
    {
        $messages = [];

        // 处理 MarkdownContent：提取纯文本和媒体附件
        if ($channelMessage->contentType === ContentType::Markdown) {
            $content = $channelMessage->getContent();
            if ($content instanceof MarkdownContent) {
                // 1. 创建文本消息（纯文本）
                $plainText = $content->getPlainText();
                if ($plainText !== '') {
                    $messages[] = [
                        'msgtype' => 'text',
                        'text'    => [
                            'content' => mb_substr($plainText, 0, 2048), // 企业微信限制2048字节
                        ],
                    ];
                }

                // 2. 处理媒体附件（图片、视频、音频）
                $attachments = $content->getMediaAttachments();
                foreach ($attachments as $attachment) {
                    // 企业微信的媒体类型映射：audio -> voice
                    $wechatType = $attachment['type'] === 'audio' ? 'voice' : $attachment['type'];
                    $mediaId    = $this->uploadMediaFromUrl($attachment['url'], $wechatType);

                    if ($mediaId) {
                        $message = [
                            'msgtype' => $wechatType,
                        ];

                        switch ($attachment['type']) {
                            case 'image':
                                $message['image'] = [
                                    'media_id' => $mediaId,
                                ];
                                break;
                            case 'video':
                                $message['video'] = [
                                    'media_id' => $mediaId,
                                ];
                                break;
                            case 'audio':
                                $message['voice'] = [
                                    'media_id' => $mediaId,
                                ];
                                break;
                        }

                        $messages[] = $message;

                    } else {
                        Log::warning('上传媒体附件失败', [
                            'url'  => $attachment['url'],
                            'type' => $attachment['type'],
                        ]);
                    }
                }

                return $messages;
            }
        }

        // 处理普通文本消息
        if ($channelMessage->contentType === ContentType::Text) {
            $content = $channelMessage->getContent();
            if ($content instanceof TextContent) {
                $messages[] = [
                    'msgtype' => 'text',
                    'text'    => [
                        'content' => mb_substr($content->text, 0, 2048),
                    ],
                ];
            }
        }

        // TODO 转换更多消息类型

        return $messages;
    }

    /**
     * 从 URL 下载文件并上传到企业微信临时素材
     *
     * @param  string  $url  媒体文件 URL
     * @param  string  $type  媒体类型：image, video, voice
     *
     * @return string|null 返回 media_id，失败返回 null
     */
    protected function uploadMediaFromUrl(string $url, string $type) : ?string
    {
        if (empty($url)) {
            return null;
        }

        try {
            // 1. 下载文件到临时目录
            $tempFile = $this->downloadFileFromUrl($url);
            if (!$tempFile) {
                Log::error('下载媒体文件失败', ['url' => $url]);
                return null;
            }

            // 2. 上传到企业微信
            $mediaId = $this->uploadMediaToWorkWechat($tempFile, $type);

            // 3. 清理临时文件
            @unlink($tempFile);

            return $mediaId;
        } catch (Exception $e) {
            Log::error('上传媒体文件异常', [
                'url'   => $url,
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 从 URL 下载文件到 Laravel 文件系统临时目录
     *
     * @param  string  $url  文件 URL
     *
     * @return string|null 临时文件完整路径（供 fopen 使用），失败返回 null
     */
    protected function downloadFileFromUrl(string $url) : ?string
    {
        try {
            $client   = new GuzzleClient(['http_errors' => false]);
            $response = $client->get($url);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $content = $response->getBody()->getContents();
            if (empty($content)) {
                return null;
            }

            // 使用 Laravel 文件系统保存到临时目录
            $relativePath = 'workwechat/temp/'.Str::uuid().'.tmp';
            Storage::disk('local')->put($relativePath, $content);

            return Storage::disk('local')->path($relativePath);
        } catch (Exception $e) {
            Log::error('下载文件异常', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * 上传媒体文件到企业微信临时素材
     *
     * @param  string  $filePath  本地文件路径
     * @param  string  $type  媒体类型：image, video, voice
     *
     * @return string|null 返回 media_id，失败返回 null
     */
    protected function uploadMediaToWorkWechat(string $filePath, string $type) : ?string
    {
        if (!file_exists($filePath)) {
            return null;
        }

        try {
            // 获取 access_token
            $accessToken = $this->workWechatApp->getAccessToken()->getToken();

            // 根据文档：https://developer.work.weixin.qq.com/document/path/90253
            // POST /cgi-bin/media/upload?access_token=ACCESS_TOKEN&type=TYPE
            // 使用 multipart/form-data，字段名为 "media"

            $client   = new GuzzleClient(['base_uri' => 'https://qyapi.weixin.qq.com']);
            $filename = basename($filePath);

            $response = $client->post('/cgi-bin/media/upload', [
                'query'       => [
                    'access_token' => $accessToken,
                    'type'         => $type,
                ],
                'multipart'   => [
                    [
                        'name'     => 'media',
                        'contents' => fopen($filePath, 'r'),
                        'filename' => $filename,
                    ],
                ],
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                Log::error('上传媒体文件失败', [
                    'file'   => $filePath,
                    'type'   => $type,
                    'status' => $response->getStatusCode(),
                ]);
                return null;
            }

            $data = json_decode($response->getBody()->getContents(), true);
            if (isset($data['errcode']) && $data['errcode'] !== 0) {
                Log::error('上传媒体文件API错误', [
                    'file'    => $filePath,
                    'type'    => $type,
                    'errcode' => $data['errcode'] ?? 0,
                    'errmsg'  => $data['errmsg'] ?? 'Unknown error',
                ]);
                return null;
            }

            $mediaId = $data['media_id'] ?? null;
            if ($mediaId) {
                Log::info('上传媒体文件成功', [
                    'file'     => $filePath,
                    'type'     => $type,
                    'media_id' => $mediaId,
                ]);
            }

            return $mediaId;
        } catch (Exception $e) {
            Log::error('上传媒体文件异常', [
                'file'  => $filePath,
                'type'  => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    public function transferToHumanQueuing(ConversationData $conversation) : void
    {

        $api = $this->workWechatApp->getClient();
        // /cgi-bin/kf/service_state/trans
        // 文档
        // https://developer.work.weixin.qq.com/document/path/94669#%E5%8F%98%E6%9B%B4%E4%BC%9A%E8%AF%9D%E7%8A%B6%E6%80%81
        $data = [
            'open_kfid'       => $conversation->channelAppId,
            'external_userid' => $conversation->user->getID(),
            'service_state'   => 2,
            //'servicer_userid' =>null,
            // 'service_state'   => 3,
            // 'servicer_userid' =>'liuyongjian',
        ];
        Log::info('发起转接人工', $data);
        $response = $api->postJson('/cgi-bin/kf/service_state/trans', $data);
        Log::info('发起转接人工返回', [
            'status'  => $response->getStatusCode(),
            'content' => $response->getContent(),
        ]);
    }

    public function getSuccessResponse() : Response
    {
        return response()->json([
            'errcode' => 0,
            'errmsg'  => 'ok',
        ]);
    }
}
