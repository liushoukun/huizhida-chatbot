<?php

namespace HuiZhiDa\Processor\Infrastructure\Adapters;

use Exception;
use GuzzleHttp\Client;
use HuiZhiDa\Processor\Domain\Contracts\AgentAdapterInterface;
use HuiZhiDa\Processor\Domain\Data\AgentChatRequest;
use HuiZhiDa\Processor\Domain\Data\AgentChatResponse;
use HuiZhiDa\Core\Domain\Conversation\DTO\AgentMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\MediaContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\ImageContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\FileContent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Coze 远程智能体适配器
 */
class CozeAdapter implements AgentAdapterInterface
{
    protected Client $client;
    protected array  $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initialize($config);
    }

    public function initialize(array $config) : void
    {
        $botId   = $config['bot_id'] ?? '';
        $token   = $config['token'] ?? '';
        $apiBase = $config['api_base'] ?? 'https://api.coze.cn';

        if (empty($token) || empty($botId)) {
            throw new InvalidArgumentException('Coze API key is required');
        }

        $this->client = new Client([
            'base_uri' => rtrim($apiBase, '/'),
            'timeout'  => $config['timeout'] ?? 30,
            'headers'  => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    public function chat(AgentChatRequest $request) : AgentChatResponse
    {
        $botId = $this->config['bot_id'] ?? '';
        if (empty($botId)) {
            throw new InvalidArgumentException('Coze bot_id is required');
        }

        $chatResponse = new AgentChatResponse();
        // 1、创建会话
        // 获取会话 如果没有会话先创建会话
        // 优先使用 agent_conversation_id，如果没有则使用 conversation_id

        $agentConversationId = $request->agentConversationId;
        if (!$agentConversationId) {
            Log::debug('Coze 创建会话',
                ['agentConversationId' => $request->agentConversationId, 'conversationId' => $request->conversationId]);

            $response = $this->client->post('v1/conversation/create', [
                'json'        => [
                    'bot_id' => $botId,
                ],
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException("Coze API error: ".$response->getStatusCode());
            }
            $data                = json_decode($response->getBody()->getContents(), true);
            $agentConversationId = $data['data']['id'] ?? null;
            if (!$agentConversationId) {
                throw new RuntimeException("Coze API error: Conversation ID not found");
            }

        }

        $chatResponse->agentConversationId = $agentConversationId;


        // 2、发起对话
        Log::debug('Coze 发起对话', ['agentConversationId' => $agentConversationId, 'conversationId' => $request->conversationId]);
        $messages = [];
        foreach ($request->messages as $msg) {
            $cozeMessage = $this->convertMessageToCozeFormat($msg);
            if ($cozeMessage) {
                $messages[] = $cozeMessage;
            }
        }



        try {

            // 发起对话
            $response = $this->client->post('v3/chat', [
                'query' => [
                    'conversation_id' => $agentConversationId,
                ],
                'json'  => [
                    'bot_id'              => $botId,
                    'user_id'             => $request->user->getID(),// TODO 需要替换
                    'additional_messages' => $messages,
                    'stream'              => false,
                ],
            ]);

            // 流式读取响应
            $chatResponseData = json_decode($response->getBody()->getContents(), true);
            // 获取对话ID
            $chatId = $chatResponseData['data']['id'];

            $queryIndex = 0;


            $agentMessages = [];

            while (true) {
                sleep(2);
                $queryIndex++;
                // 查询对话状态
                $chatRetrieveResponse     = $this->client->post('v3/chat/retrieve', [
                    'query' => [
                        'conversation_id' => $agentConversationId,
                        'chat_id'         => $chatId,
                    ],
                    'json'  => [
                        'bot_id'              => $botId,
                        'user_id'             => 'user_id',// TODO 需要替换
                        'additional_messages' => $messages,
                        'stream'              => false,
                    ],
                    // 'stream' => true, // 启用流式响应
                ]);
                $chatRetrieveResponseData = json_decode($chatRetrieveResponse->getBody()->getContents(), true);

                // 如果 最终状态
                $finalStatusList = [
                    'completed',
                    'failed',
                    'requires_action',
                    'canceled'
                ];

                if (in_array($chatRetrieveResponseData['data']['status'], $finalStatusList)) {
                    $chatRetrieveStatus = $chatRetrieveResponseData['data']['status'];
                    break;
                }
                if ($queryIndex > 100) {
                    throw new RuntimeException("Coze API error: Query timeout");
                }
            }
            // 3.  获取回复结果
            if ($chatRetrieveStatus === 'completed') {
                Log::debug('Coze 获取回复结果',
                    ['agentConversationId' => $agentConversationId, 'conversationId' => $request->conversationId]);
                $chatResultResponse     = $this->client->post('v3/chat/message/list', [
                    'query' => [
                        'conversation_id' => $agentConversationId,
                        'chat_id'         => $chatId,
                    ],
                ]);
                $chatResultResponseData = json_decode($chatResultResponse->getBody()->getContents(), true);

                Log::debug('Coze 获取回复结果', ['chatResultResponseData' => $chatResultResponseData]);
                $responseMessages = collect($chatResultResponseData['data'] ?? [])->filter(function ($message) {
                    return $message['role'] === 'assistant'
                           && $message['type'] === 'answer';
                })->values();

                foreach ($responseMessages as $responseMessage) {
                    // TODO 解析Message 根据类型解析
                    // 如果有媒体对象 需要解析媒体对象
                    $agentMessage              = new AgentMessage();
                    $agentMessage->messageType = MessageType::Chat;

                    $agentMessage->setContentData(ContentType::Markdown, [
                        'text' => $responseMessage['content'] ?? '',
                    ]);

                    $agentMessages[] = $agentMessage;
                }

            }

            // 回复结果不要 markdown 格式 TODO

            Log::debug('Coze 返回结果', [
                'agentConversationId' => $agentConversationId,
                'conversationId'      => $request->conversationId,
                'agentMessages'       => $agentMessages,
            ]);


            $chatResponse->messages            = $agentMessages;
            $chatResponse->conversationId      = $request->conversationId;
            $chatResponse->agentConversationId = $agentConversationId;

            return $chatResponse;
        } catch (Exception $e) {

            Log::error('Coze API error', [
                'error'  => $e->getMessage(),
                'config' => $this->config,
            ]);
            throw new RuntimeException("Coze API error: ".$e->getMessage());
        }
    }

    public function healthCheck() : bool
    {
        // Coze 没有专门的健康检查接口
        return true;
    }



    /**
     * 将消息转换为 Coze 格式
     *
     * @param  ChannelMessage  $message
     *
     * @return array|null
     */
    protected function convertMessageToCozeFormat(ChannelMessage $message) : ?array
    {
        // 处理文本消息
        if ($message->contentType === ContentType::Text) {
            $content = $message->getContent();
            return [
                'role'         => 'user',
                'content'      => $content?->text ?? '',
                'content_type' => 'text',
            ];
        }

        // 处理媒体类型消息（图片、文件等）
        if (in_array($message->contentType, [ContentType::Image, ContentType::File, ContentType::Video, ContentType::Voice])) {
            return $this->convertMediaMessageToCozeFormat($message);
        }

        // 处理组合消息（包含文本和媒体）
        if ($message->contentType === ContentType::Combination) {
            return $this->convertCombinationMessageToCozeFormat($message);
        }

        Log::warning('Coze 不支持的消息类型', ['contentType' => $message->contentType->value]);
        return null;
    }

    /**
     * 将媒体消息转换为 Coze 格式
     *
     * @param  ChannelMessage  $message
     *
     * @return array|null
     */
    protected function convertMediaMessageToCozeFormat(ChannelMessage $message) : ?array
    {
        $content = $message->getContent();
        if (!$content instanceof MediaContent) {
            Log::warning('Coze 媒体消息内容类型错误', ['contentType' => $message->contentType->value]);
            return null;
        }

        // 上传文件获取 file_id
        $fileId = $this->uploadFileToCoze($content);
        if (!$fileId) {
            Log::error('Coze 上传文件失败', ['contentType' => $message->contentType->value]);
            return null;
        }

        // 根据媒体类型确定 type
        $mediaType = match ($message->contentType) {
            ContentType::Image => 'image',
            default            => 'file',
        };

        // 构建内容数组
        $contentArray = [
            [
                'type'    => $mediaType,
                'file_id' => $fileId,
            ],
        ];

        return [
            'role'         => 'user',
            'content_type' => 'object_string',
            'content'      => json_encode($contentArray, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * 将组合消息转换为 Coze 格式（包含文本和媒体）
     *
     * @param  ChannelMessage  $message
     *
     * @return array|null
     */
    protected function convertCombinationMessageToCozeFormat(ChannelMessage $message) : ?array
    {
        // 组合消息的 content 应该是一个数组，包含多个内容项
        $contentData = $message->content ?? [];
        if (!is_array($contentData)) {
            Log::warning('Coze 组合消息内容格式错误');
            return null;
        }

        $contentArray = [];

        // 遍历内容项
        foreach ($contentData as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemType = $item['type'] ?? null;
            if (!$itemType) {
                continue;
            }

            // 处理文本
            if ($itemType === 'text' || $itemType === ContentType::Text->value) {
                $text = $item['text'] ?? $item['content'] ?? '';
                if (!empty($text)) {
                    $contentArray[] = [
                        'type' => 'text',
                        'text' => $text,
                    ];
                }
            }

            // 处理媒体类型
            if (in_array($itemType, ['image', 'file', 'video', 'voice']) ||
                in_array($itemType, [ContentType::Image->value, ContentType::File->value, ContentType::Video->value, ContentType::Voice->value])) {
                // 从 item 构建 MediaContent 对象
                $mediaContent = $this->buildMediaContentFromItem($item);
                if ($mediaContent) {
                    $fileId = $this->uploadFileToCoze($mediaContent);
                    if ($fileId) {
                        $mediaType = match ($itemType) {
                            'image', ContentType::Image->value => 'image',
                            'file', ContentType::File->value   => 'file',
                            'video', ContentType::Video->value  => 'video',
                            'voice', ContentType::Voice->value  => 'voice',
                            default                             => 'file',
                        };
                        $contentArray[] = [
                            'type'    => $mediaType,
                            'file_id' => $fileId,
                        ];
                    }
                }
            }
        }

        if (empty($contentArray)) {
            Log::warning('Coze 组合消息内容为空');
            return null;
        }

        return [
            'role'         => 'user',
            'content_type' => 'object_string',
            'content'      => json_encode($contentArray, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * 从数组项构建 MediaContent 对象
     *
     * @param  array  $item
     *
     * @return MediaContent|null
     */
    protected function buildMediaContentFromItem(array $item) : ?MediaContent
    {
        $type = $item['type'] ?? '';
        $url  = $item['url'] ?? '';
        $path = $item['path'] ?? '';
        $disk = $item['disk'] ?? 'public';

        if (empty($url) && empty($path)) {
            return null;
        }

        // 根据类型创建对应的 MediaContent
        if (in_array($type, ['image', ContentType::Image->value])) {
            $content = new ImageContent();
        } elseif (in_array($type, ['file', ContentType::File->value])) {
            $content = new FileContent();
        } else {
            $content = new MediaContent();
        }

        // 只有 ImageContent 和 FileContent 有 url 属性
        if ($content instanceof ImageContent || $content instanceof FileContent) {
            $content->url = $url;
        }
        $content->path = $path;
        $content->disk = $disk;
        $content->type = $type;

        return $content;
    }

    /**
     * 上传文件到 Coze 并获取 file_id
     *
     * @param  MediaContent  $mediaContent
     *
     * @return string|null
     */
    protected function uploadFileToCoze(MediaContent $mediaContent) : ?string
    {
        try {
            // 获取文件内容
            $fileContent = $this->getFileContent($mediaContent);
            if (!$fileContent) {
                $url = ($mediaContent instanceof ImageContent || $mediaContent instanceof FileContent) 
                    ? ($mediaContent->url ?? '') 
                    : '';
                Log::error('Coze 获取文件内容失败', [
                    'url'  => $url,
                    'path' => $mediaContent->path,
                    'disk' => $mediaContent->disk,
                ]);
                return null;
            }

            $stream = Storage::disk($mediaContent->disk)->readStream($mediaContent->path);

            // 获取文件名
            $filename = $this->getFilename($mediaContent);




            // 上传文件到 Coze
            // 注意：需要移除默认的 Content-Type header，使用 multipart/form-data
            $response = $this->client->post('v1/files/upload', [
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => $stream,
                        'filename' => $filename,
                    ],
                ],
                'headers'   => [
                    'Authorization' => 'Bearer '.$this->config['token'],
                    // 不设置 Content-Type，让 Guzzle 自动设置为 multipart/form-data
                ],
                'http_errors' => false,
            ]);



            if ($response->getStatusCode() !== 200) {
                $errorBody = $response->getBody()->getContents();
                Log::error('Coze 上传文件失败', [
                    'status' => $response->getStatusCode(),
                    'error'  => $errorBody,
                ]);
                return null;
            }

            $data = json_decode($response->getBody()->getContents(), true);
            $fileId = $data['data']['id'] ?? $data['data']['file_id'] ?? null;

            if (!$fileId) {
                Log::error('Coze 上传文件响应中未找到 file_id', ['response' => $data]);
                return null;
            }

            Log::debug('Coze 上传文件成功', ['file_id' => $fileId, 'filename' => $filename]);
            return $fileId;
        } catch (Exception $e) {
            Log::error('Coze 上传文件异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * 获取文件内容
     *
     * @param  MediaContent  $mediaContent
     *
     * @return string|null
     */
    protected function getFileContent(MediaContent $mediaContent) : ?string
    {
        // 优先使用 path 和 disk
        if (!empty($mediaContent->path) && !empty($mediaContent->disk)) {
            try {
                $disk = Storage::disk($mediaContent->disk);
                if ($disk->exists($mediaContent->path)) {
                    return $disk->get($mediaContent->path);
                }
            } catch (Exception $e) {
                Log::warning('Coze 从存储读取文件失败', [
                    'path'  => $mediaContent->path,
                    'disk'  => $mediaContent->disk,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 如果 path 不可用，尝试从 URL 下载
        $url = ($mediaContent instanceof ImageContent || $mediaContent instanceof FileContent) 
            ? ($mediaContent->url ?? '') 
            : '';
        if (!empty($url)) {
            try {
                $response = (new Client())->get($url, [
                    'timeout' => 30,
                ]);
                if ($response->getStatusCode() === 200) {
                    return $response->getBody()->getContents();
                }
            } catch (Exception $e) {
                Log::warning('Coze 从 URL 下载文件失败', [
                    'url'   => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /**
     * 获取文件名
     *
     * @param  MediaContent  $mediaContent
     *
     * @return string
     */
    protected function getFilename(MediaContent $mediaContent) : string
    {
        // 如果是 FileContent，使用 filename
        if ($mediaContent instanceof FileContent && !empty($mediaContent->filename)) {
            return $mediaContent->filename;
        }

        // 从 path 提取文件名
        if (!empty($mediaContent->path)) {
            return basename($mediaContent->path);
        }

        // 从 URL 提取文件名
        $url = ($mediaContent instanceof ImageContent || $mediaContent instanceof FileContent) 
            ? ($mediaContent->url ?? '') 
            : '';
        if (!empty($url)) {
            $parsedUrl = parse_url($url);
            $path      = $parsedUrl['path'] ?? '';
            if (!empty($path)) {
                return basename($path);
            }
        }

        // 根据类型生成默认文件名
        $extension = match ($mediaContent->type) {
            'image' => 'jpg',
            'voice' => 'mp3',
            'video' => 'mp4',
            default => 'bin',
        };

        return 'file_'.time().'.'.$extension;
    }
}
