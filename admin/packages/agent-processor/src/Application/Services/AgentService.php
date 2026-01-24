<?php

namespace HuiZhiDa\AgentProcessor\Application\Services;

use Exception;
use HuiZhiDa\AgentProcessor\Domain\Contracts\AgentAdapterInterface;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatRequest;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatResponse;
use HuiZhiDa\AgentProcessor\Domain\Data\Message;
use HuiZhiDa\AgentProcessor\Infrastructure\Adapters\AgentAdapterFactory;
use HuiZhiDa\Core\Domain\Agent\Models\Agent;
use HuiZhiDa\Core\Domain\Agent\Repositories\AgentRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
     * @param  ChannelMessage[]  $messages  消息列表
     * @param  ConversationData  $conversation  会话数据
     * @param  int  $agentId  智能体ID
     *
     * @return ChatResponse 响应数据
     * @throws Exception
     */
    public function processMessages(array $messages, ConversationData $conversation, int $agentId) : ChatResponse
    {

        try {
            // 1. 获取智能体配置
            $agent = $this->agentRepository->find($agentId);

            if (!$agent || !$agent->isEnabled()) {
                Log::error("智能体不存在或已禁用", ['agent_id' => $agentId]);
                throw new RuntimeException("Agent {$agentId} not found or disabled");
            }


            // 2. 创建智能体适配器
            $adapter = $this->adapterFactory->create($agent);

            // 3. 构建聊天请求
            $request = $this->buildChatRequest($messages, $conversation);
            Log::debug('Building chat request', ['conversation_id' => $conversation->conversationId]);

            // 4. 调用智能体
            $timeout = config('agent-processor.agent.timeout', 30);
            Log::debug('Agent processing completed', ['conversation_id' => $conversation->conversationId]);
            // 5. 格式化响应，返回数据
            return $this->callWithTimeout($adapter, $request, $timeout);


            return $response;

        } catch (Exception $e) {
            throw $e;
            Log::error('Agent processing failed', [
                'agent_id'        => $agentId,
                'conversation_id' => $conversation->conversationId,
                'error'           => $e->getMessage(),
            ]);

            // 尝试使用降级智能体
            if (isset($agent) && $agent->fallback_agent_id) {
                return $this->processWithFallback($messages, $conversation, $agent->fallback_agent_id);
            }


        }
    }

    /**
     * 使用降级智能体处理
     */
    protected function processWithFallback(array $messages, array $conversation, int $fallbackAgentId) : array
    {
        Log::info('Using fallback agent', ['fallback_agent_id' => $fallbackAgentId]);
        return $this->processMessages($messages, $conversation, $fallbackAgentId);
    }

    /**
     *
     * @param  array  $messages
     * @param  ConversationData  $conversation
     *
     * @return ChatRequest
     */
    protected function buildChatRequest(array $messages, ConversationData $conversation) : ChatRequest
    {

        $chatRequest = new ChatRequest();

        $chatRequest->conversationId      = $conversation->conversationId;
        $chatRequest->agentConversationId = $conversation->agentConversationId;
        $chatRequest->messages            = $messages;
        $chatRequest->user                = $conversation->user;
        return $chatRequest;
    }

    /**
     * 提取消息内容
     */
    protected function extractMessageContent(array|Message $message) : string
    {
        // 如果是 Message 对象，使用其 getText 方法
        if ($message instanceof Message) {
            return $message->getText();
        }

        // 如果是数组，按原逻辑处理
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
    protected function callWithTimeout(AgentAdapterInterface $adapter, ChatRequest $request, int $timeout) : ChatResponse
    {
        $startTime = microtime(true);

        try {
            Log::debug('智能体对话开始', ['conversation_id' => $request->conversationId]);
            $response = $adapter->chat($request);
            $duration = microtime(true) - $startTime;
            Log::debug('智能体对话结束', ['conversation_id' => $request->conversationId, 'duration' => round($duration, 2)]);


            return $response;
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            if ($duration >= $timeout) {
                throw new RuntimeException("Agent timeout after {$timeout}s");
            }
            throw $e;
        }
    }

    /**
     * 格式化响应
     */
    protected function formatResponse(ChatResponse $response, Agent $agent) : array
    {
        return [
            'reply'                 => $response->reply,
            'reply_type'            => $response->replyType,
            'rich_content'          => $response->richContent,
            'should_transfer'       => $response->shouldTransfer,
            'transfer_reason'       => $response->transferReason,
            'confidence'            => $response->confidence,
            'processed_by'          => $agent->name ?? 'unknown',
            'agent_conversation_id' => $response->agentConversationId,
            'metadata'              => $response->metadata,
        ];
    }
}
