<?php

namespace HuiZhiDa\Gateway\Infrastructure\Adapters;

use EasyWeChat\Work\Application;
use Exception;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\EventContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\FileContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\ImageContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\TextContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\VideoContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\VoiceContent;
use HuiZhiDa\Core\Domain\Conversation\Enums\EventType;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationOutputQueue;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use HuiZhiDa\Gateway\Domain\Contracts\ChannelAdapterInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RedJasmine\Support\Domain\Data\UserData;
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
        // 获取推送的事件
        $server  = $this->workWechatApp->getServer();
        $api     = $this->workWechatApp->getClient();
        $message = $server->getDecryptedMessage();

        // 消息应该固定为 kf_msg_or_event
        if ($message->Event !== 'kf_msg_or_event') {
            throw new Exception('Invalid message event');
        }

        $token    = $message->Token;
        $openKfId = $message->OpenKfId;

        // 获取上次的cursor，实现增量获取
        $cacheKey = "workwechat:sync_cursor:{$openKfId}";
        $cursor   = Cache::get($cacheKey);

        // 调用sync_msg接口获取消息
        $response = $api->postJson('/cgi-bin/kf/sync_msg', [
            'token'     => $token,
            'open_kfid' => $openKfId,
            'cursor'    => $cursor, // 使用上次的cursor实现增量获取
            'limit'     => 1000, // 每次最多获取1000条
        ]);

        // 解析返回数据
        $responseData = json_decode($response->getContent(), true);
        Log::info('sync_msg response', $responseData);

        // 检查错误
        if (isset($responseData['errcode']) && $responseData['errcode'] !== 0) {
            throw new Exception('Sync message failed: '.($responseData['errmsg'] ?? 'Unknown error'));
        }

        // 保存新的cursor
        if (isset($responseData['next_cursor']) && !empty($responseData['next_cursor'])) {
            Cache::put($cacheKey, $responseData['next_cursor'], now()->addDays(30));
        }

        // 获取消息列表
        $msgList = $responseData['msg_list'] ?? [];
        if (empty($msgList)) {
            throw new Exception('No messages in response');
        }
        // 支持的消息类型
        $supportedMsgTypes = ['text', 'image', 'voice', 'video', 'file', 'event'];

        // 处理消息列表，返回最后一条消息（最新的）
        $messages = [];
        foreach ($msgList as $msgData) {
            $msgType = $msgData['msgtype'] ?? 'text';

            // 过滤不支持的消息类型
            if (!in_array($msgType, $supportedMsgTypes, true)) {
                Log::debug('跳过不支持的消息类型', [
                    'msgtype' => $msgType,
                    'msgid'   => $msgData['msgid'] ?? '',
                ]);
                continue;
            }

            // 转换消息
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
        $message                        = new ChannelMessage();
        $message->messageId             = $message->getMessageId();
        $message->channelConversationId = $msgData['external_userid'];// 企业微信是一个用户是一个会话,会话状态支持 轮换，结束后，可以重新接入
        $message->channelMessageId      = $msgData['msgid'] ?? '';
        $message->messageType           = $this->mapMessageType($msgData['msgtype'] ?? 'text');
        $message->contentType           = $this->mapContentType($msgData['msgtype'] ?? 'text');
        $message->timestamp             = $msgData['send_time'] ?? time();
        $message->rawData               = json_encode($msgData, JSON_UNESCAPED_UNICODE);
        $message->channelAppId          = $openKfId;


        // 解析发送者信息
        $message->sender = UserData::from([
            'type'     => 'user',
            'id'       => $msgData['external_userid'],
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
     * @return TextContent|ImageContent|VoiceContent|FileContent|VideoContent|EventContent|null
     * @throws Exception
     */
    protected function parseMessageContent(array $msgData, ContentType $contentType)
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
     * @throws TransportExceptionInterface
     */
    protected function downloadMedia(string $mediaId, string $type) : ?string
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
            $content     = $response->getBody()->getContents();
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
     * @return EventContent
     * @throws Exception
     */
    protected function parseEventContent(array $msgData) : EventContent
    {
        $content = new EventContent();

        // 获取事件类型，可能直接在顶层或 event 字段中
        $eventType = $msgData['event'] ?? '';

        // 获取事件数据，可能在 event_data 字段中，也可能直接在顶层
        $eventData = $msgData['event_data'] ?? $msgData;

        // 根据企业微信事件类型映射到 EventType 枚举
        if ($eventType === 'status_change_event' || isset($eventData['service_state'])) {
            // 会话状态变更事件
            $serviceState = $eventData['service_state'] ?? null;

            // 根据 service_state 映射到相应的事件类型
            // service_state: 2 表示转人工排队
            // service_state: 3 或 4 表示会话结束
            // service_state: 1 表示已转人工（有客服接入）
            if ($serviceState === 2) {
                $content->event = EventType::TransferToHumanQueue;
            } elseif ($serviceState === 1) {
                $content->event = EventType::TransferToHuman;
            } elseif ($serviceState === 3 || $serviceState === 4) {
                $content->event = EventType::Closed;
            } else {
                // 未知状态，记录日志并使用默认值
                Log::warning('未知的会话状态', [
                    'service_state' => $serviceState,
                    'event_data'    => $eventData,
                    'msg_data'      => $msgData,
                ]);
                // 默认使用 Closed
                $content->event = EventType::Closed;
            }
        } else {
            // 其他事件类型，记录日志
            Log::warning('未知的事件类型', [
                'event'      => $eventType,
                'event_data' => $eventData,
                'msg_data'   => $msgData,
            ]);
            // 默认使用 Closed
            $content->event = EventType::Closed;
        }

        return $content;
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
        $workWechatMessage = $this->convertToWorkWechatMessage($message);

        $workWechatMessage['touser']    = $conversationAnswer->user->getID();
        $workWechatMessage['open_kfid'] = $conversationAnswer->channelAppId;
        $workWechatMessage['msgid']     = $message->getMessageId();
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

    protected function convertToWorkWechatMessage(ChannelMessage $channelMessage) : array
    {
        $message            = [];
        $message['msgtype'] = $channelMessage->contentType->value;
        if ($channelMessage->contentType === ContentType::Text) {
            $content = $channelMessage->getContent();
            if ($content instanceof TextContent) {
                $message['text'] = [
                    'content' => $content->text,
                ];
            }
        }

        // TODO 转换更多消息

        return $message;

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

    public function getSuccessResponse() : array
    {
        return [
            'errcode' => 0,
            'errmsg'  => 'ok',
        ];
    }
}
