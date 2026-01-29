<?php

namespace HuiZhiDa\Gateway\Infrastructure\Adapters;

use Exception;
use HuiZhiDa\Processor\Domain\Data\AgentChatResponse;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\TextContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationOutputQueue;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use HuiZhiDa\Gateway\Domain\Contracts\ChannelAdapterInterface;
use HuiZhiDa\Gateway\Domain\DTO\CallbackPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function health(Request $request) : Response
    {
        return response()->json();
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

    public function extractCallbackPayload(Request $request, string $channelId) : ?CallbackPayload
    {
        return null;
    }

    public function fetchAndParseMessages(CallbackPayload $payload) : array
    {
        return [];
    }

    public function parseMessages(Request $request) : array
    {
        $rawData = $request->getContent();
        $data    = $request->all();


        $message                        = new ChannelMessage();
        $message->channelConversationId = $data['conversation_id'] ?? uniqid('api_msg_', true);
        $message->channelChatId         = $data['chat_id'] ?? uniqid('api_msg_', true);
        $message->channelMessageId      = $data['message_id'] ?? uniqid('api_msg_', true);
        $message->messageType           = MessageType::from($data['message_type']);
        $message->contentType           = ContentType::from($data['content_type']);
        $message->timestamp             = $data['timestamp'] ?? time();
        $message->rawData               = $rawData;

        $message->channelAppId = 'api';
        // 解析用户信息

        $message->sender = UserData::from($data['user'] ?? [
            'type' => 'guest',
            'id'   => Str::random(),
        ]);


        $message->setContentData($message->contentType, $data['content'] ?? null);


        return [$message];
    }

    public function sendMessages(ConversationOutputQueue $conversationOutputQueue) : void
    {

        foreach ($conversationOutputQueue->messages as $message){
            Log::debug('输出消息',$message->toArray());
        }
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
                'text' => $message->content->text,
            ];
        } else {
            // 默认文本消息
            $data['content'] = [
                'type' => 'text',
                'text' => '',
            ];
        }

        return $data;
    }

    public function transferToHumanQueuing(ConversationData $conversation) : void
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
            'text'     => MessageType::Chat,
            'image'    => MessageType::Chat,
            'voice'    => MessageType::Chat,
            'video'    => MessageType::Chat,
            'file'     => MessageType::Chat,
            'link'     => MessageType::Chat,
            'location' => MessageType::Chat,
            'event'    => MessageType::Event,
        ];

        return $map[strtolower($type)] ?? MessageType::Chat;
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
}
