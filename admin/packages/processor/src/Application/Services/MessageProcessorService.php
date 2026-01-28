<?php

namespace HuiZhiDa\Processor\Application\Services;

use Exception;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\EventContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationOutputQueue;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationStatus;
use HuiZhiDa\Core\Domain\Conversation\Enums\EventType;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use HuiZhiDa\Processor\Domain\Data\PreCheckResult;
use HuiZhiDa\Processor\Domain\Enums\ActionType;
use HuiZhiDa\Processor\Domain\Services\PreCheckService;
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
        $conversationId = $event->conversationId;
        Log::withContext([
            'conversationId' => $event->conversationId,
            'eventId'        => $event->id,
        ]);

        try {
            // 1. 获取未处理消息
            $messages = $this->conversationApplicationService->getPendingInputMessages($conversationId);
            if (empty($messages)) {
                Log::info('No PendingInputMessages');
                return;
            }

            $conversation = $this->conversationApplicationService->get($conversationId);

            Log::debug('获取未处理消息', ['messages_count' => count($messages)]);

            // 2. 分离事件消息和对话消息
            $eventMessages = [];
            $chatMessages  = [];
            foreach ($messages as $message) {
                if ($message->messageType === MessageType::Event) {
                    $eventMessages[] = $message;
                } else {
                    $chatMessages[] = $message;
                }
            }

            // 3. 先处理事件消息
            if (!empty($eventMessages)) {
                Log::debug('处理事件消息', ['conversation_id' => $conversationId, 'event_count' => count($eventMessages)]);
                $this->processEventMessages($conversation, $eventMessages);
            }

            // 4. 处理对话消息
            if (!empty($chatMessages)) {
                Log::debug('处理对话消息', ['conversation_id' => $conversationId, 'chat_count' => count($chatMessages)]);
                $this->processChatMessages($conversation, $chatMessages);
            }

            // 5. 移除已处理的消息
            $this->removeProcessedMessages($conversationId);

        } catch (Exception $e) {
            Log::error('处理会话事件失败', [
                'conversation_id' => $conversationId,
                'error'           => $e->getMessage(),
                'trace'           => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * 处理事件消息
     *
     * @param  ConversationData  $conversation
     * @param  ChannelMessage[]  $eventMessages
     *
     * @return void
     * @throws Exception
     */
    protected function processEventMessages(ConversationData $conversation, array $eventMessages) : void
    {

        foreach ($eventMessages as $message) {
            // 根据事件类型处理
            // TODO: 根据实际的事件类型进行相应处理
            // 例如：close_conversation, transfer_human, state_change 等
            Log::debug('处理事件消息', [
                'conversation_id' => $conversation->conversationId,
                'message_id'      => $message->messageId,
                'content_type'    => $message->contentType->value ?? null,
            ]);

            // 这里可以根据消息内容类型或内容数据来判断具体的事件类型
            // 暂时记录日志，后续根据实际需求完善
        }
    }

    /**
     * 处理对话消息
     *
     * @param  ConversationData  $conversation
     * @param  ChannelMessage[]  $chatMessages
     *
     * @return void
     */
    protected function processChatMessages(ConversationData $conversation, array $chatMessages) : void
    {
        $conversationId = $conversation->conversationId;

        // 1. 规则预判断（传入消息组）
        $checkResult = $this->preCheckService->check($chatMessages, $conversation);
        Log::debug('规则预判断', ['conversation_id' => $conversationId, 'check_result' => $checkResult]);

        // 忽略消息
        if ($checkResult->actionType === ActionType::Ignore) {
            Log::debug('跳过处理');
            return;
        }

        if ($checkResult->actionType === ActionType::TransferHuman) {
            // 转人工，推入转人工队列
            $this->requestTransferHuman($conversation, $checkResult);
            return;
        }

        // 2. 获取渠道绑定的智能体
        $channelId = $conversation->channelId;
        if (!$conversation->channelId) {
            Log::warning('Conversation missing channel_id', ['conversation_id' => $conversationId]);
            $this->requestTransferHuman($conversation, $checkResult);
            return;
        }

        $agentId = $this->getAgentIdForChannel($channelId);
        if (!$agentId) {
            Log::warning('No agent found for channel', ['channel_id' => $channelId, 'conversation_id' => $conversationId]);
            $this->requestTransferHuman($conversation, $checkResult);
            return;
        }

        // 3. 调用智能体处理消息
        Log::debug('调用智能体处理消息', ['conversation_id' => $conversationId, 'agent_id' => $agentId]);

        try {
            $awnerData = $this->agentService->processMessages($chatMessages, $conversation, $agentId);

            // 保存智能体会话ID
            $this->conversationApplicationService->updateAgentConversationId(
                $awnerData->conversationId,
                $awnerData->agentConversationId
            );

            // TODO 根据智能体消息，确认是否需要转人工

            $this->publishOutput($conversation, $awnerData);

        } catch (Exception $e) {
            Log::error('智能体处理失败', [
                'conversation_id' => $conversationId,
                'agent_id'        => $agentId,
                'error'           => $e->getMessage(),
            ]);

            // 智能体处理失败，转人工
            $this->requestTransferHuman($conversation);
        }
    }


    /**
     * 移除已处理的消息
     */
    protected function removeProcessedMessages(string $conversationId) : void
    {
        $this->conversationApplicationService->removePendingInputMessages($conversationId);
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
    protected function requestTransferHuman(ConversationData $conversation, ?PreCheckResult $checkResult = null) : void
    {
        $conversationId = $conversation->conversationId;

        $this->conversationApplicationService->transfer($conversation->conversationId, ConversationStatus::HumanQueuing);

        $outputQueue = new ConversationOutputQueue();

        $outputQueue->conversationId        = $conversation->conversationId;
        $outputQueue->channelConversationId = $conversation->channelConversationId;
        $outputQueue->channelId             = $conversation->channelId;
        $outputQueue->user                  = $conversation->user;
        $outputQueue->channelAppId          = $conversation->channelAppId;

        // TODO 更具配置判断是否转入工处理队列，还是指定人员处理
        $channelMessage                        = new ChannelMessage();
        $channelMessage->channelId             = $conversation->channelId;
        $channelMessage->channelConversationId = $conversation->channelConversationId;
        $channelMessage->channelAppId          = $conversation->channelAppId;
        $channelMessage->messageType           = MessageType::Event;
        $channelMessage->contentType           = ContentType::Event;
        $channelMessage->setContentData(ContentType::Event,
            EventContent::from([
                'event' => EventType::TransferToHumanQueue,
            ])->toArray()
        );

        $outputQueue->messages = [$channelMessage];
        //

        $this->messageQueue->publish(ConversationQueueType::Outputs, $outputQueue);

        Log::info('转换人工处理');
    }

    /**
     * 发布回复消息
     */
    protected function publishOutput(ConversationData $conversation, ConversationOutputQueue $conversationAnswerData) : void
    {
        Log::info('发布 OUTPUT');
        $this->messageQueue->publish(ConversationQueueType::Outputs, $conversationAnswerData);


    }
}
