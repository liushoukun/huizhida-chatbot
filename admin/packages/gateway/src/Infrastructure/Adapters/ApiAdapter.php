<?php

namespace HuiZhiDa\Gateway\Infrastructure\Adapters;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Gateway\Domain\Contracts\ChannelAdapterInterface;
use HuiZhiDa\Gateway\Domain\Models\Message;
use HuiZhiDa\Gateway\Domain\Models\UserInfo;
use HuiZhiDa\Gateway\Domain\Models\MessageContent;
use InvalidArgumentException;
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

    public function parseMessage(string $rawData) : Message
    {
        $data = json_decode($rawData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON format: '.json_last_error_msg());
        }

        $message                   = Message::createIncoming();
        $message->messageId        = $data['message_id'] ?? uniqid('api_msg_', true);
        $message->channelMessageId = $data['message_id'] ?? $message->messageId;
        $message->messageType      = $this->mapMessageType($data['msgtype'] ??  'text');
        $message->timestamp        = $data['timestamp'] ?? time();
        $message->rawData          = $rawData;

        // 解析用户信息
        $message->user = new UserInfo();
        $userData                     = is_array($data['user']) ? $data['user'] : [];

        $message->user->channelUserId = $userData['id'] ?? $userData['user_id'] ?? $userData['channel_user_id'] ?? '';
        $message->user->nickname      = $userData['nickname'] ?? $userData['name'] ?? '';
        $message->user->avatar        = $userData['avatar'] ?? '';
        $message->user->isVip         = $userData['is_vip'] ?? false;
        $message->user->tags          = $userData['tags'] ?? [];

        // 解析消息内容
        $message->content = new MessageContent();
        $contentData      = $data['content'] ?? $data;

        if (isset($contentData['text'])) {

            $message->content->text = $contentData['text'];
        }

        if (isset($contentData['media_url']) || isset($contentData['mediaUrl'])) {
            $message->content->mediaUrl  = $contentData['media_url'] ?? $contentData['mediaUrl'];
            $message->content->mediaType = $contentData['media_type'] ?? $contentData['mediaType'] ?? $message->messageType;
        }

        // 保存额外数据
        $message->content->extra = $contentData['extra'] ?? [];
        if (isset($data['conversation_id'])) {
            $message->channelConversationId = $data['conversation_id'];
        }

        return $message;
    }

    public function convertToChannelFormat(Message $message) : array
    {
        if (!$message->isOutgoing()) {
            throw new InvalidArgumentException('Message must be outgoing type');
        }

        $data = [
            'message_id' => $message->messageId,
            'timestamp'  => $message->timestamp,
        ];

        if ($message->conversationId) {
            $data['conversation_id'] = $message->conversationId;
        }

        if ($message->replyType === 'text') {
            $data['content'] = [
                'type' => 'text',
                'text' => $message->reply,
            ];
        } elseif ($message->replyType === 'rich' && !empty($message->richContent)) {
            $data['content'] = [
                'type' => 'rich',
                'data' => $message->richContent,
            ];
        } else {
            // 默认文本消息
            $data['content'] = [
                'type' => 'text',
                'text' => $message->reply ?? '',
            ];
        }

        return $data;
    }

    public function sendMessage(Message $message) : void
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
    protected function mapMessageType(string $type) : string
    {
        $map = [
            'text'     => Message::TYPE_TEXT,
            'image'    => Message::TYPE_IMAGE,
            'voice'    => Message::TYPE_VOICE,
            'video'    => Message::TYPE_VIDEO,
            'file'     => Message::TYPE_FILE,
            'link'     => Message::TYPE_LINK,
            'location' => Message::TYPE_LOCATION,
            'event'    => Message::TYPE_EVENT,
            'rich'     => Message::TYPE_RICH,
        ];

        return $map[strtolower($type)] ?? Message::TYPE_TEXT;
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
