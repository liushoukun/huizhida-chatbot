<?php

namespace HuiZhiDa\AgentProcessor\Application\Services;

use Exception;
use HuiZhiDa\Core\Domain\Message\Contracts\MessageQueueInterface;
use HuiZhiDa\Gateway\Domain\Services\ConversationService;
use HuiZhiDa\Gateway\Infrastructure\Queue\RedisQueue;
use HuiZhiDa\Core\Domain\Message\DTO\ConversationEvent;
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
        protected MessageQueueInterface $messageQueue,
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


        $conversationId = $event->conversationId;

        try {
            // 1. 从Redis ZSET获取会话的所有未处理消息
            $messages = $this->getUnprocessedMessages($event->conversationId);

            if (empty($messages)) {
                Log::info('No unprocessed messages', ['conversation_id' => $conversationId]);
                return;
            }

            // 2. 获取会话信息
            $conversation = $this->conversationService->get($conversationId);

            if (!$conversation) {
                Log::warning('Conversation not found', ['conversation_id' => $conversationId]);
                return;
            }

            // 3. 规则预判断
            $firstMessage = $messages[0];
            $checkResult  = $this->preCheckService->check($firstMessage, $conversation);

            if ($checkResult['action'] === PreCheckService::ACTION_SKIP) {
                // 跳过处理，移除消息
                $this->removeProcessedMessages($conversationId);
                return;
            }

            if ($checkResult['action'] === PreCheckService::ACTION_TRANSFER_HUMAN) {
                // 转人工，推入转人工队列
                $this->requestTransferHuman($conversation, $checkResult['reason'], 'rule');
                $this->removeProcessedMessages($conversationId);
                return;
            }

            // 4. 获取渠道绑定的智能体
            $channelId = $conversation['channel_id'] ?? null;

            if (!$channelId) {
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
            try {
                $response = $this->agentService->processMessages($messages, $conversation, $agentId);

                // 6. 处理响应
                if ($response['should_transfer'] ?? false) {
                    // 智能体建议转人工
                    $this->requestTransferHuman(
                        $conversation,
                        $response['transfer_reason'] ?? 'agent_suggestion',
                        'agent'
                    );
                } else {
                    // 推入回复队列
                    $this->publishReply($conversation, $response);
                }

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
     * 获取未处理的消息
     */
    protected function getUnprocessedMessages(string $conversationId) : array
    {
        if ($this->messageQueue instanceof RedisQueue) {
            return $this->messageQueue->getConversationMessages($conversationId);
        }

        // 降级处理：直接从Redis读取
        $key      = config('agent-processor.redis.conversation_messages_prefix', 'conversation:messages:').$conversationId;
        $messages = Redis::connection(config('agent-processor.redis.connection', 'default'))
                         ->zrange($key, 0, -1);

        $result = [];
        foreach ($messages as $message) {
            $decoded = json_decode($message, true);
            if ($decoded !== null) {
                $result[] = $decoded;
            }
        }

        return $result;
    }

    /**
     * 移除已处理的消息
     */
    protected function removeProcessedMessages(string $conversationId) : void
    {
        $maxScore = microtime(true);

        if ($this->messageQueue instanceof RedisQueue) {
            $this->messageQueue->removeConversationMessages($conversationId, $maxScore);
            return;
        }

        // 降级处理：直接从Redis删除
        $key = config('agent-processor.redis.conversation_messages_prefix', 'conversation:messages:').$conversationId;
        Redis::connection(config('agent-processor.redis.connection', 'default'))
             ->zremrangebyscore($key, '-inf', $maxScore);
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
    protected function publishReply(array $conversation, array $response) : void
    {
        $conversationId = $conversation['conversation_id'] ?? '';
        $channel        = $conversation['channel_type'] ?? '';

        $replyData = [
            'conversation_id' => $conversationId,
            'channel'         => $channel,
            'reply'           => $response['reply'] ?? '',
            'reply_type'      => $response['reply_type'] ?? 'text',
            'rich_content'    => $response['rich_content'] ?? null,
            'timestamp'       => time(),
        ];

        $queue = config('agent-processor.queue.outgoing_messages_queue', 'outgoing_messages');
        $this->messageQueue->publish($queue, $replyData);

        Log::info('Reply published', [
            'conversation_id' => $conversationId,
            'reply_type'      => $replyData['reply_type'],
        ]);
    }
}
