<?php

namespace HuiZhiDa\Gateway\Infrastructure\Adapters;

use Exception;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\ImageContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\TextContent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use HuiZhiDa\Core\Domain\Conversation\Enums\UserType;
use HuiZhiDa\Gateway\Domain\Contracts\ChannelAdapterInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RedJasmine\Support\Domain\Data\UserData;
use RuntimeException;

class ApiAdapter implements ChannelAdapterInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function verifySignature(Request $request) : bool
    {
        // 如果配置了 api_key 和 api_secret，进行签名验证
        $apiKey    = $this->config['api_key'] ?? '';
        $apiSecret = $this->config['api_secret'] ?? '';

        if (empty($apiKey) || empty($apiSecret)) {
            // 如果没有配置密钥，跳过验证
            return true;
        }

        // 获取请求头中的签名
        $signature = $request->header('X-API-Signature');
        $timestamp = $request->header('X-API-Timestamp');
        $nonce     = $request->header('X-API-Nonce');

        if (empty($signature) || empty($timestamp)) {
            return false;
        }

        // 构建签名字符串：timestamp + nonce + body + secret
        $body              = $request->getContent();
        $signString        = $timestamp.($nonce ?? '').$body.$apiSecret;
        $expectedSignature = hash_hmac('sha256', $signString, $apiKey);

        // 使用时间戳验证防止重放攻击（可选，这里简化处理）
        $requestTime = (int) $timestamp;
        $currentTime = time();
        if (abs($currentTime - $requestTime) > 300) { // 5分钟有效期
            return false;
        }

        return hash_equals($expectedSignature, $signature);
    }

    public function parseMessage(Request $request) : ChannelMessage
    {
        $rawData = $request->getContent();
        $data    = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON format: '.json_last_error_msg());
        }

        $message                        = new ChannelMessage();
        $message->channelConversationId = $data['conversation_id'] ?? uniqid('api_msg_', true);
        $message->channelChatId         = $data['chat_id'] ?? uniqid('api_msg_', true);
        $message->channelMessageId      = $data['message_id'] ?? uniqid('api_msg_', true);
        $message->messageType           = $this->mapMessageType($data['message_type']);
        $message->contentType           = $this->mapContentType($data['content_type']);
        $message->timestamp             = $data['timestamp'] ?? time();
        $message->rawData               = $rawData;


        // 解析用户信息

        $message->sender = UserData::from($data['user'] ?? [
            'type' => 'guest',
            'id'   => Str::uuid(),
        ]);
       

        // 解析消息内容
        $contentData      = $data['content'] ?? $data;
        $contentType      = $message->contentType;
        $message->content = $contentData;
        // TODO 转换格式

        $message->setContentData($contentType, $contentData);


        return $message;
    }

    public function convertToChannelFormat(ChannelMessage $message) : array
    {
        $data = [
            'message_id' => $message->messageId,
            'timestamp'  => $message->timestamp,
        ];

        if ($message->conversationId) {
            $data['conversation_id'] = $message->conversationId;
        }

        if ($message->contentType === ContentType::Text && $message->content instanceof TextContent) {
            $data['content'] = [
                'type' => 'text',
                'text' => $message->content->content,
            ];
        } elseif ($message->contentType === ContentType::Image && $message->content instanceof ImageContent) {
            $data['content'] = [
                'type' => 'image',
                'url'  => $message->content->url,
            ];
            if ($message->content->width) {
                $data['content']['width'] = $message->content->width;
            }
            if ($message->content->height) {
                $data['content']['height'] = $message->content->height;
            }
        } else {
            // 默认文本消息
            $data['content'] = [
                'type' => 'text',
                'text' => '',
            ];
        }

        return $data;
    }

    public function sendMessage(ChannelMessage $message) : void
    {
        $apiUrl = $this->config['api_url'] ?? '';

        if (empty($apiUrl)) {
            throw new RuntimeException('API URL is not configured');
        }

        $payload = $this->convertToChannelFormat($message);

        // 构建请求头
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        // 如果配置了 API Key，添加到请求头
        $apiKey = $this->config['api_key'] ?? '';
        if (!empty($apiKey)) {
            $headers['X-API-Key'] = $apiKey;
        }

        // 如果配置了 API Secret，生成签名
        $apiSecret = $this->config['api_secret'] ?? '';
        if (!empty($apiSecret) && !empty($apiKey)) {
            $timestamp  = (string) time();
            $nonce      = uniqid();
            $body       = json_encode($payload);
            $signString = $timestamp.$nonce.$body.$apiSecret;
            $signature  = hash_hmac('sha256', $signString, $apiKey);

            $headers['X-API-Timestamp'] = $timestamp;
            $headers['X-API-Nonce']     = $nonce;
            $headers['X-API-Signature'] = $signature;
        }

        try {
            $response = Http::withHeaders($headers)
                            ->timeout(30)
                            ->post($apiUrl, $payload);

            if (!$response->successful()) {
                Log::error('API adapter send message failed', [
                    'url'      => $apiUrl,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
                throw new RuntimeException('Failed to send message to API: '.$response->body());
            }
        } catch (Exception $e) {
            Log::error('API adapter send message exception', [
                'url'   => $apiUrl,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function transferToQueue(string $conversationId, string $priority = 'normal') : void
    {
        $apiUrl = $this->config['api_url'] ?? '';

        if (empty($apiUrl)) {
            throw new RuntimeException('API URL is not configured');
        }

        // 构建转接请求
        $payload = [
            'action'          => 'transfer',
            'conversation_id' => $conversationId,
            'mode'            => 'queue',
            'priority'        => $priority,
            'timestamp'       => time(),
        ];

        $this->sendApiRequest($apiUrl.'/transfer', $payload);
    }

    public function transferToSpecific(string $conversationId, string $servicerId, string $priority = 'normal') : void
    {
        $apiUrl = $this->config['api_url'] ?? '';

        if (empty($apiUrl)) {
            throw new RuntimeException('API URL is not configured');
        }

        // 构建转接请求
        $payload = [
            'action'          => 'transfer',
            'conversation_id' => $conversationId,
            'mode'            => 'specific',
            'servicer_id'     => $servicerId,
            'priority'        => $priority,
            'timestamp'       => time(),
        ];

        $this->sendApiRequest($apiUrl.'/transfer', $payload);
    }

    public function getSuccessResponse() : array
    {
        return [
            'success'   => true,
            'code'      => 200,
            'message'   => 'ok',
            'timestamp' => time(),
        ];
    }

    /**
     * 映射消息类型
     */
    protected function mapMessageType(string $type) : MessageType
    {
        $map = [
            'text'         => MessageType::Question,
            'image'        => MessageType::Question,
            'voice'        => MessageType::Question,
            'video'        => MessageType::Question,
            'file'         => MessageType::Question,
            'link'         => MessageType::Question,
            'location'     => MessageType::Question,
            'event'        => MessageType::Event,
            'answer'       => MessageType::Answer,
            'notification' => MessageType::Notification,
            'tip'          => MessageType::Tip,
        ];

        return $map[strtolower($type)] ?? MessageType::Question;
    }

    /**
     * 映射内容类型
     */
    protected function mapContentType(string $type) : ContentType
    {
        $map = [
            'text'  => ContentType::Text,
            'image' => ContentType::Image,
            'voice' => ContentType::Voice,
            'video' => ContentType::Video,
            'file'  => ContentType::File,
            'card'  => ContentType::Card,
            'event' => ContentType::Event,
        ];

        return $map[strtolower($type)] ?? ContentType::Text;
    }


    /**
     * 发送 API 请求（用于转接等操作）
     */
    protected function sendApiRequest(string $url, array $payload) : void
    {
        $headers = [
            'Content-Type' => 'application/json',
            'Accept'       => 'application/json',
        ];

        // 如果配置了 API Key，添加到请求头
        $apiKey = $this->config['api_key'] ?? '';
        if (!empty($apiKey)) {
            $headers['X-API-Key'] = $apiKey;
        }

        // 如果配置了 API Secret，生成签名
        $apiSecret = $this->config['api_secret'] ?? '';
        if (!empty($apiSecret) && !empty($apiKey)) {
            $timestamp  = (string) time();
            $nonce      = uniqid();
            $body       = json_encode($payload);
            $signString = $timestamp.$nonce.$body.$apiSecret;
            $signature  = hash_hmac('sha256', $signString, $apiKey);

            $headers['X-API-Timestamp'] = $timestamp;
            $headers['X-API-Nonce']     = $nonce;
            $headers['X-API-Signature'] = $signature;
        }

        try {
            $response = Http::withHeaders($headers)
                            ->timeout(30)
                            ->post($url, $payload);

            if (!$response->successful()) {
                Log::error('API adapter request failed', [
                    'url'      => $url,
                    'status'   => $response->status(),
                    'response' => $response->body(),
                ]);
                throw new RuntimeException('Failed to send API request: '.$response->body());
            }
        } catch (Exception $e) {
            Log::error('API adapter request exception', [
                'url'   => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
