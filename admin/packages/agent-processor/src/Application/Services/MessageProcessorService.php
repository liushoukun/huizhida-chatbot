<?php

namespace HuiZhiDa\AgentProcessor\Application\Services;

use Exception;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatResponse;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use HuiZhiDa\Core\Domain\Conversation\Services\ConversationService;
use HuiZhiDa\Core\Domain\Conversation\Services\MessageService;
use HuiZhiDa\Gateway\Infrastructure\Queue\RedisQueue;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * 消息处理器服务
 * 核心服务，负责处理会话事件
 */
class MessageProcessorService
{
    public function __construct(
        protected ConversationApplicationService $conversationApplicationService,
        protected ConversationQueueInterface $messageQueue,
        protected ConversationService $conversationService,
        protected PreCheckService $preCheckService,
        protected AgentService $agentService
    ) {
    }

    /**
     * 处理会话事件
     *
     * @param  ConversationEvent  $event  事件数据
     *
     * @return void
     * @throws Exception
     */
    public function processConversationEvent(ConversationEvent $event) : void
    {



        // 每个会话加锁
        $conversationId = $event->conversationId;
        Log::debug('开始处理会话事件', ['conversation_id' => $conversationId]);

        try {
            // 1. 获取未处理消息
            // 按当前时间去获取 未处理消息 TODO
            $messages = $this->conversationApplicationService->getPendingMessages($conversationId);


            Log::debug('获取未处理消息', ['conversation_id' => $conversationId, 'messages_count' => count($messages)]);


            if (empty($messages)) {
                Log::info('No unprocessed messages', ['conversation_id' => $conversationId]);
                return;
            }

            // 2. 获取会话信息
            $conversation = $this->conversationService->get($conversationId);
            Log::debug('获取会话信息', ['conversation_id' => $conversationId, 'conversation' => $conversation]);


            if (!$conversation) {
                Log::warning('Conversation not found', ['conversation_id' => $conversationId]);
                return;
            }


            // 3. 规则预判断（传入消息组）
            $checkResult = $this->preCheckService->check($messages, $conversation);
            Log::debug('规则预判断', ['conversation_id' => $conversationId, 'check_result' => $checkResult]);

            // 忽略消息
            if ($checkResult->actionType === ActionType::Ignore) {
                // 跳过处理，移除消息
                $this->removeProcessedMessages($conversationId);
                return;
            }

            if ($checkResult->actionType === ActionType::TransferHuman) {
                // 转人工，推入转人工队列
                $this->requestTransferHuman($conversation, $checkResult['reason'], 'rule');
                $this->removeProcessedMessages($conversationId);
                return;
            }

            // 4. 获取渠道绑定的智能体

            $channelId = $conversation->channelId;
            if (!$conversation->channelId) {
                Log::warning('Conversation missing channel_id', ['conversation_id' => $conversationId]);
                $this->requestTransferHuman($conversation, 'no_channel_id', 'rule');
                $this->removeProcessedMessages($conversationId);
                return;
            }

            $agentId = $this->getAgentIdForChannel($channelId);

            if (!$agentId) {
                Log::warning('No agent found for channel', ['channel_id' => $channelId, 'conversation_id' => $conversationId]);
                $this->requestTransferHuman($conversation, 'no_agent', 'rule');
                $this->removeProcessedMessages($conversationId);
                return;
            }

            // 5. 调用智能体处理消息
            Log::debug('调用智能体处理消息', ['conversation_id' => $conversationId, 'agent_id' => $agentId]);

            try {
                $chatResponse = $this->agentService->processMessages($messages, $conversation, $agentId);

                // TODO 根据智能体消息，确认是否需要转人工


                $this->publishReply($conversation, $chatResponse);

                // 7. 移除已处理的消息
                $this->removeProcessedMessages($conversationId);

                // 8. 更新会话（包括智能体会话ID）
                $updateData = [
                    'updated_at' => now(),
                ];

                // 如果智能体返回了会话ID，保存它
                if (isset($response['agent_conversation_id']) && !empty($response['agent_conversation_id'])) {
                    $updateData['agent_conversation_id'] = $response['agent_conversation_id'];
                }

                $this->conversationService->update($conversationId, $updateData);

            } catch (Exception $e) {
                Log::error('Agent processing failed', [
                    'conversation_id' => $conversationId,
                    'agent_id'        => $agentId,
                    'error'           => $e->getMessage(),
                ]);

                // 智能体处理失败，转人工
                $this->requestTransferHuman($conversation, 'agent_fail', 'rule');
                $this->removeProcessedMessages($conversationId);
            }

        } catch (Exception $e) {
            Log::error('Process conversation event failed', [
                'conversation_id' => $conversationId,
                'error'           => $e->getMessage(),
                'trace'           => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }


    /**
     * 移除已处理的消息
     */
    protected function removeProcessedMessages(string $conversationId) : void
    {
        $this->conversationApplicationService->removePendingMessages($conversationId);
    }

    /**
     * 获取渠道绑定的智能体ID
     */
    protected function getAgentIdForChannel(string|int $channelId) : ?int
    {
        $channel = DB::table('channels')->where('id', $channelId)->first();
        if ($channel && $channel->agent_id) {
            return (int) $channel->agent_id;
        }

        return null;
    }

    /**
     * 请求转人工
     */
    protected function requestTransferHuman(array $conversation, string $reason, string $source) : void
    {
        $conversationId = $conversation['conversation_id'] ?? '';
        $channel        = $conversation['channel_type'] ?? '';

        // 更新会话状态
        $this->conversationService->updateStatus($conversationId, 'pending_human', $reason, $source);

        // 推入转人工队列
        $transferData = [
            'conversation_id' => $conversationId,
            'channel'         => $channel,
            'reason'          => $reason,
            'source'          => $source,
            'priority'        => ($conversation['is_vip'] ?? false) ? 'high' : 'normal',
            'timestamp'       => time(),
        ];

        $queue = config('agent-processor.queue.transfer_requests_queue', 'transfer_requests');
        $this->messageQueue->publish($queue, $transferData);

        Log::info('Transfer human requested', [
            'conversation_id' => $conversationId,
            'reason'          => $reason,
            'source'          => $source,
        ]);
    }

    /**
     * 发布回复消息
     */
    protected function publishReply(ConversationData $conversation, ChatResponse $chatResponse) : void
    {


        $this->messageQueue->publish(ConversationQueueType::Sending, $chatResponse);

        Log::info('Reply published', [
            'conversation_id' => $conversation->conversationId,
        ]);
    }
}
