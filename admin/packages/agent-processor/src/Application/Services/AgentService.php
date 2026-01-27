<?php

namespace HuiZhiDa\AgentProcessor\Application\Services;

use Exception;
use HuiZhiDa\AgentProcessor\Domain\Contracts\AgentAdapterInterface;
use HuiZhiDa\AgentProcessor\Domain\Data\AgentChatRequest;
use HuiZhiDa\AgentProcessor\Domain\Data\AgentChatResponse;
use HuiZhiDa\AgentProcessor\Domain\Data\Message;
use HuiZhiDa\AgentProcessor\Infrastructure\Adapters\AgentAdapterFactory;
use HuiZhiDa\Core\Domain\Agent\Models\Agent;
use HuiZhiDa\Core\Domain\Agent\Repositories\AgentRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationAnswerData;
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
     * @return ConversationAnswerData 回答数据
     * @throws Exception
     */
    public function processMessages(array $messages, ConversationData $conversation, int $agentId) : ConversationAnswerData
    {

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
        $agentChatResponse = $this->agentChat($adapter, $request, $timeout);

        return $this->formatResponse($conversation, $agentChatResponse);

    }


    /**
     *
     * @param  array  $messages
     * @param  ConversationData  $conversation
     *
     * @return AgentChatRequest
     */
    protected function buildChatRequest(array $messages, ConversationData $conversation) : AgentChatRequest
    {

        $chatRequest = new AgentChatRequest();

        $chatRequest->conversationId      = $conversation->conversationId;
        $chatRequest->agentConversationId = $conversation->agentConversationId;
        $chatRequest->messages            = $messages;
        $chatRequest->user                = $conversation->user;
        return $chatRequest;
    }


    /**
     * 带超时的调用
     */
    protected function agentChat(AgentAdapterInterface $adapter, AgentChatRequest $request, int $timeout) : AgentChatResponse
    {
        $startTime = microtime(true);

        Log::debug('智能体对话开始', ['conversation_id' => $request->conversationId]);
        $response = $adapter->chat($request);
        $duration = microtime(true) - $startTime;
        Log::debug('智能体对话结束', [
            'conversation_id'       => $request->conversationId,
            'agent_conversation_id' => $response->agentConversationId,
            'duration'              => round($duration, 2)
        ]);

        return $response;
    }

    /**
     * 格式化响应
     */
    protected function formatResponse(ConversationData $conversation, AgentChatResponse $response) : ConversationAnswerData
    {
        $answer = new ConversationAnswerData();

        $answer->conversationId        = $conversation->conversationId;
        $answer->channelConversationId = $conversation->channelConversationId;
        $answer->channelId             = $conversation->channelId;

        $answer->user         = $conversation->user;
        $answer->channelAppId = $conversation->channelAppId;

        // 返回信息
        $answer->agentConversationId = $response->agentConversationId;
        foreach ($response->messages as $message) {
            $channelMessage                        = ChannelMessage::from($message->toArray());
            $channelMessage->channelId             = $answer->channelId;
            $channelMessage->channelConversationId = $answer->channelConversationId;
            $channelMessage->channelAppId          = $answer->channelAppId;
            $answer->messages[]                    = $channelMessage;
        }

        return $answer;

    }
}
