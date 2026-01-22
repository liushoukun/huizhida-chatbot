<?php

namespace HuiZhiDa\AgentProcessor\Infrastructure\Adapters;

use HuiZhiDa\AgentProcessor\Domain\Contracts\AgentAdapterInterface;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatRequest;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatResponse;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * Coze 远程智能体适配器
 */
class CozeAdapter implements AgentAdapterInterface
{
    protected Client $client;
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initialize($config);
    }

    public function initialize(array $config): void
    {
        $apiKey = $config['api_key'] ?? '';
        $apiBase = $config['api_base'] ?? 'https://api.coze.cn/v1';

        if (empty($apiKey)) {
            throw new \InvalidArgumentException('Coze API key is required');
        }

        $this->client = new Client([
            'base_uri' => rtrim($apiBase, '/'),
            'timeout' => $config['timeout'] ?? 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    public function chat(ChatRequest $request): ChatResponse
    {
        $botId = $this->config['bot_id'] ?? '';
        // 优先使用 agent_conversation_id，如果没有则使用 conversation_id
        $agentConversationId = $request->agentConversationId ?? $request->conversationId;

        if (empty($botId)) {
            throw new \InvalidArgumentException('Coze bot_id is required');
        }

        // 构建消息内容
        $content = '';
        if (!empty($request->messages)) {
            foreach ($request->messages as $msg) {
                $text = $msg['content']['text'] ?? $msg['content'] ?? '';
                if (!empty($text)) {
                    $content .= $text . "\n";
                }
            }
        }
        $content = trim($content);

        try {
            $response = $this->client->post('/chat', [
                'json' => [
                    'bot_id' => $botId,
                    'conversation_id' => $agentConversationId,
                    'content' => $content,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            $reply = $data['content'] ?? '';
            $shouldTransfer = $this->shouldTransfer($reply, $request);
            
            // 如果API返回了新的会话ID，使用它
            $returnedConversationId = $data['conversation_id'] ?? $agentConversationId;

            return new ChatResponse(
                reply: $reply,
                replyType: 'text',
                shouldTransfer: $shouldTransfer,
                transferReason: $shouldTransfer ? 'low_confidence' : null,
                confidence: 0.9,
                agentConversationId: $returnedConversationId,
                metadata: [
                    'provider' => 'coze',
                    'bot_id' => $botId,
                ],
            );
        } catch (\Exception $e) {
            Log::error('Coze API error', [
                'error' => $e->getMessage(),
                'config' => $this->config,
            ]);
            throw new \RuntimeException("Coze API error: " . $e->getMessage());
        }
    }

    public function healthCheck(): bool
    {
        // Coze 没有专门的健康检查接口
        return true;
    }

    protected function shouldTransfer(string $reply, ChatRequest $request): bool
    {
        if (empty($reply) || mb_strlen($reply) < 5) {
            return true;
        }
        return false;
    }
}
