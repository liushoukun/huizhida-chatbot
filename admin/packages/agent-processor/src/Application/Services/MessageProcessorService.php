<?php

namespace HuiZhiDa\AgentProcessor\Application\Services;

use Exception;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationAnswerData;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 消息处理器服务
 * 核心服务，负责处理会话事件
 */
class MessageProcessorService
{
    public function __construct(
        protected ConversationApplicationService $conversationApplicationService,
        protected ConversationQueueInterface $messageQueue,
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
            if (empty($messages)) {
                Log::info('No unprocessed messages', ['conversation_id' => $conversationId]);
                return;
            }

            Log::debug('获取未处理消息', ['conversation_id' => $conversationId, 'messages_count' => count($messages)]);


            // 2. 获取会话信息
            $conversation = $this->conversationApplicationService->get($conversationId);
            Log::debug('获取会话信息', $conversation->toArray());

            // 3. 规则预判断（传入消息组）
            $checkResult = $this->preCheckService->check($messages, $conversation);
            Log::debug('规则预判断', ['conversation_id' => $conversationId, 'check_result' => $checkResult]);
            // 忽略消息
            if ($checkResult->actionType === ActionType::Ignore) {
                Log::debug('调过处理');

                // 跳过处理，移除消息
                $this->removeProcessedMessages($conversationId);
                return;
            }

            if ($checkResult->actionType === ActionType::TransferHuman) {
                // 转人工，推入转人工队列
                $this->requestTransferHuman($conversation, $checkResult);
                $this->removeProcessedMessages($conversationId);
                return;
            }

            // 4. 获取渠道绑定的智能体

            $channelId = $conversation->channelId;
            if (!$conversation->channelId) {
                Log::warning('Conversation missing channel_id', ['conversation_id' => $conversationId]);
                $this->requestTransferHuman($conversation, $checkResult);
                $this->removeProcessedMessages($conversationId);
                return;
            }

            $agentId = $this->getAgentIdForChannel($channelId);

            if (!$agentId) {
                Log::warning('No agent found for channel', ['channel_id' => $channelId, 'conversation_id' => $conversationId]);
                $this->requestTransferHuman($conversation, $checkResult);
                $this->removeProcessedMessages($conversationId);
                return;
            }

            // 5. 调用智能体处理消息
            Log::debug('调用智能体处理消息', ['conversation_id' => $conversationId, 'agent_id' => $agentId]);

            try {
                $awnerData = $this->agentService->processMessages($messages, $conversation, $agentId);

                // 保存智能体会话ID
                $this->conversationApplicationService->updateAgentConversationId(
                    $awnerData->conversationId,
                    $awnerData->agentConversationId
                );


                // TODO 根据智能体消息，确认是否需要转人工


                $this->publishAnswer($conversation, $awnerData);

                // 7. 移除已处理的消息
                $this->removeProcessedMessages($conversationId);


            } catch (Exception $e) {
                Log::error('智能体处理失败', [
                    'conversation_id' => $conversationId,
                    'agent_id'        => $agentId,
                    'error'           => $e->getMessage(),
                ]);

                // 智能体处理失败，转人工
                $this->requestTransferHuman($conversation);
                $this->removeProcessedMessages($conversationId);
            }

        } catch (Exception $e) {
            Log::error('处理会话事件失败');
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
    protected function requestTransferHuman(ConversationData $conversation, ?CheckResult $checkResult = null) : void
    {
        $conversationId = $conversation->conversationId;

        $this->conversationApplicationService->transfer($conversation->conversationId, ConversationStatus::HumanQueuing);

        $this->messageQueue->publish(ConversationQueueType::Transfer, $conversation);

        Log::info('转换人工处理');
    }

    /**
     * 发布回复消息
     */
    protected function publishAnswer(ConversationData $conversation, ConversationAnswerData $conversationAnswerData) : void
    {
        Log::info('发布回答队列', [
            'conversation_id' => $conversation->conversationId,
        ]);
        $this->messageQueue->publish(ConversationQueueType::Sending, $conversationAnswerData);


    }
}
