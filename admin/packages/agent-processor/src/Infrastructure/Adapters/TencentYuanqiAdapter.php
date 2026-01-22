<?php

namespace HuiZhiDa\AgentProcessor\Infrastructure\Adapters;

use HuiZhiDa\AgentProcessor\Domain\Contracts\AgentAdapterInterface;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatRequest;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatResponse;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

/**
 * 腾讯元启远程智能体适配器
 */
class TencentYuanqiAdapter implements AgentAdapterInterface
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
        $apiBase = $config['api_base'] ?? 'https://hunyuan.tencentcloudapi.com';

        if (empty($apiKey)) {
            throw new \InvalidArgumentException('Tencent Yuanqi API key is required');
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
        $model = $this->config['model'] ?? 'hunyuan-lite';
        $systemPrompt = $this->config['system_prompt'] ?? '你是一个专业的客服助手。';
        $temperature = $this->config['temperature'] ?? 0.7;
        $maxTokens = $this->config['max_tokens'] ?? 2000;

        // 构建消息列表
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        
        // 添加历史消息
        if (!empty($request->history)) {
            foreach ($request->history as $msg) {
                if (isset($msg['role']) && isset($msg['content'])) {
                    $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
                }
            }
        }

        // 添加当前消息
        if (!empty($request->messages)) {
            foreach ($request->messages as $msg) {
                $content = $msg['content']['text'] ?? $msg['content'] ?? '';
                if (!empty($content)) {
                    $messages[] = ['role' => 'user', 'content' => $content];
                }
            }
        }

        try {
            $response = $this->client->post('/v1/chat/completions', [
                'json' => [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                    'max_tokens' => $maxTokens,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            
            $reply = $data['choices'][0]['message']['content'] ?? '';
            $shouldTransfer = $this->shouldTransfer($reply, $request);

            return new ChatResponse(
                reply: $reply,
                replyType: 'text',
                shouldTransfer: $shouldTransfer,
                transferReason: $shouldTransfer ? 'low_confidence' : null,
                confidence: 0.9,
                metadata: [
                    'model' => $model,
                    'provider' => 'tencent_yuanqi',
                ],
            );
        } catch (\Exception $e) {
            Log::error('Tencent Yuanqi API error', [
                'error' => $e->getMessage(),
                'config' => $this->config,
            ]);
            throw new \RuntimeException("Tencent Yuanqi API error: " . $e->getMessage());
        }
    }

    public function healthCheck(): bool
    {
        try {
            $response = $this->client->get('/v1/models', ['timeout' => 5]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function shouldTransfer(string $reply, ChatRequest $request): bool
    {
        if (empty($reply) || mb_strlen($reply) < 5) {
            return true;
        }
        return false;
    }
}
