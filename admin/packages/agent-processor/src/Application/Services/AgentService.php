<?php

namespace HuiZhiDa\AgentProcessor\Application\Services;

use HuiZhiDa\AgentProcessor\Domain\Contracts\AgentAdapterInterface;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatRequest;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatResponse;
use HuiZhiDa\AgentProcessor\Infrastructure\Adapters\AgentAdapterFactory;
use HuiZhiDa\Agent\Domain\Models\Agent;
use HuiZhiDa\Agent\Domain\Repositories\AgentRepositoryInterface;
use Illuminate\Support\Facades\Log;

/**
 * 智能体服务
 * 负责调用智能体处理消息
 */
class AgentService
{
    public function __construct(
        protected AgentRepositoryInterface $agentRepository,
        protected AgentAdapterFactory $adapterFactory
    ) {
    }

    /**
     * 调用智能体处理消息
     *
     * @param  array  $messages  消息列表
     * @param  array  $conversation  会话数据
     * @param  int  $agentId  智能体ID
     *
     * @return array 响应数据
     * @throws \Exception
     */
    public function processMessages(array $messages, array $conversation, int $agentId): array
    {
        try {
            // 1. 获取智能体配置
            $agent = $this->agentRepository->find($agentId);
            if (!$agent || !$agent->isEnabled()) {
                throw new \RuntimeException("Agent {$agentId} not found or disabled");
            }

            // 2. 创建智能体适配器
            $adapter = $this->adapterFactory->create($agent);

            // 3. 构建聊天请求
            $request = $this->buildChatRequest($messages, $conversation);

            // 4. 调用智能体
            $timeout = config('agent-processor.agent.timeout', 30);
            $response = $this->callWithTimeout($adapter, $request, $timeout);

            // 5. 格式化响应
            return $this->formatResponse($response, $agent);

        } catch (\Exception $e) {
            Log::error('Agent processing failed', [
                'agent_id' => $agentId,
                'conversation_id' => $conversation['conversation_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            // 尝试使用降级智能体
            if (isset($agent) && $agent->fallback_agent_id) {
                return $this->processWithFallback($messages, $conversation, $agent->fallback_agent_id);
            }

            throw $e;
        }
    }

    /**
     * 使用降级智能体处理
     */
    protected function processWithFallback(array $messages, array $conversation, int $fallbackAgentId): array
    {
        Log::info('Using fallback agent', ['fallback_agent_id' => $fallbackAgentId]);
        return $this->processMessages($messages, $conversation, $fallbackAgentId);
    }

    /**
     * 构建聊天请求
     */
    protected function buildChatRequest(array $messages, array $conversation): ChatRequest
    {
        $firstMessage = $messages[0] ?? [];
        $context = json_decode($conversation['context'] ?? '{}', true) ?: [];

        // 构建消息历史
        $history = [];
        if (isset($context['history'])) {
            $history = $context['history'];
        }

        // 添加当前消息
        foreach ($messages as $message) {
            $history[] = [
                'role' => 'user',
                'content' => $this->extractMessageContent($message),
            ];
        }

        return new ChatRequest(
            conversationId: $conversation['conversation_id'] ?? '',
            agentConversationId: $conversation['agent_conversation_id'] ?? null,
            messages: $messages,
            history: $history,
            context: $context,
            userInfo: [
                'channel_user_id' => $conversation['channel_user_id'] ?? '',
                'nickname' => $conversation['user_nickname'] ?? '',
                'avatar' => $conversation['user_avatar'] ?? '',
                'is_vip' => (bool) ($conversation['is_vip'] ?? false),
                'tags' => json_decode($conversation['user_tags'] ?? '[]', true) ?: [],
            ],
            timestamp: time(),
        );
    }

    /**
     * 提取消息内容
     */
    protected function extractMessageContent(array $message): string
    {
        if (isset($message['content']['text'])) {
            return $message['content']['text'];
        }
        if (isset($message['content']) && is_string($message['content'])) {
            return $message['content'];
        }
        return '';
    }

    /**
     * 带超时的调用
     */
    protected function callWithTimeout(AgentAdapterInterface $adapter, ChatRequest $request, int $timeout): ChatResponse
    {
        $startTime = microtime(true);

        try {
            $response = $adapter->chat($request);
            $duration = microtime(true) - $startTime;

            Log::info('Agent response received', [
                'duration' => round($duration, 2),
                'conversation_id' => $request->conversationId,
            ]);

            return $response;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;
            if ($duration >= $timeout) {
                throw new \RuntimeException("Agent timeout after {$timeout}s");
            }
            throw $e;
        }
    }

    /**
     * 格式化响应
     */
    protected function formatResponse(ChatResponse $response, Agent $agent): array
    {
        return [
            'reply' => $response->reply,
            'reply_type' => $response->replyType,
            'rich_content' => $response->richContent,
            'should_transfer' => $response->shouldTransfer,
            'transfer_reason' => $response->transferReason,
            'confidence' => $response->confidence,
            'processed_by' => $agent->name ?? 'unknown',
            'agent_conversation_id' => $response->agentConversationId,
            'metadata' => $response->metadata,
        ];
    }
}
