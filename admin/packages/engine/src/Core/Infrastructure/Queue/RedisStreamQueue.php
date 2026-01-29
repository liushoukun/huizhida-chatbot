<?php

namespace HuiZhiDa\Engine\Core\Infrastructure\Queue;

use Exception;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RedJasmine\Support\Foundation\Data\Data;

class RedisStreamQueue implements ConversationQueueInterface
{
    protected string $connection;
    protected string $consumerGroup;
    protected string $consumerName;
    protected array  $subscribers = [];


    public function __construct(array $config = [])
    {
        $this->connection    = $config['connection'] ?? 'default';
        $this->consumerGroup = $config['consumer_group'] ?? 'default-group';
        $this->consumerName  = $config['consumer_name'] ?? gethostname().'-'.getmypid();
    }

    /**
     * 判断当前事件是否为会话的最后一个事件
     *
     * @param  ConversationEvent  $event
     *
     * @return bool
     */
    public function isLastEvent(ConversationEvent $event) : bool
    {
        $key         = "{$event->queue->getQueueName()}-last:{$event->conversationId}";
        $lastEventId = Redis::get($key);
        return ($lastEventId === $event->id) || !$lastEventId;
    }


    public function recordLastEvent(ConversationEvent $event) : void
    {
        $key = "{$event->queue->getQueueName()}-last:{$event->conversationId}";

        Redis::setex($key, 60 * 60 * 24, $event->id);
    }


    public function publish(ConversationQueueType $queueType, Data $message) : void
    {
        try {
            $delaySeconds = $queueType->getDelaySeconds();

            // 如果配置了延时队列，使用延时队列
            if ($delaySeconds !== null) {
                $this->publishDelayed($queueType, $message, $delaySeconds);
            } else {
                // 立即发布到 Stream
                $this->publishToStream($queueType, $message);
            }
        } catch (Exception $e) {
            Log::error('Queue publish failed', [
                'queue' => $queueType->getQueueName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 发布消息到 Redis Stream
     *
     * @param  ConversationQueueType  $queueType  队列类型
     * @param  Data  $message  消息数据
     *
     * @return void
     */
    protected function publishToStream(ConversationQueueType $queueType, Data $message) : void
    {
        $redis     = Redis::connection($this->connection);
        $streamKey = $queueType->getQueueName();
        $data      = $message->toJson();

        // 使用 XADD 添加消息到 Stream
        // 格式：XADD stream * data {json}
        $messageId = $redis->xadd($streamKey, '*', ['data' => $data]);

        Log::debug('Published message to stream', [
            'queue'     => $streamKey,
            'message_id' => $messageId,
        ]);
    }

    /**
     * 发布延时消息到延时队列
     *
     * @param  ConversationQueueType  $queueType  队列类型
     * @param  Data  $message  消息数据
     * @param  int  $delaySeconds  延时秒数
     *
     * @return void
     */
    protected function publishDelayed(ConversationQueueType $queueType, Data $message, int $delaySeconds) : void
    {
        $redis          = Redis::connection($this->connection);
        $delayedQueueKey = "{$queueType->getQueueName()}:delayed";
        $executeTime    = time() + $delaySeconds; // 执行时间戳
        $data           = $message->toJson();

        // 添加延时消息到 ZSET，score 为执行时间戳
        // 防抖逻辑由消费端的 isLastEvent 方法处理，无需在此删除旧消息
        $redis->zadd($delayedQueueKey, $executeTime, $data);

        Log::debug('Published delayed message', [
            'queue'         => $queueType->getQueueName(),
            'delay_seconds' => $delaySeconds,
            'execute_time'  => $executeTime,
        ]);
    }

    public function subscribe(ConversationQueueType $queueType, callable $callback) : void
    {
        $queue           = $queueType->getQueueName();
        $this->subscribers[$queue] = $callback;
        $delayedQueueKey = "{$queue}:delayed";
        $hasDelay        = $queueType->getDelaySeconds() !== null;

        // 确保消费者组存在
        $this->ensureConsumerGroup($queueType);

        // 在 Laravel 中，通常使用队列 worker 来处理
        // 这里提供一个简单的阻塞订阅实现
        while (true) {
            try {
                // 如果配置了延时队列，先处理到期的延时消息
                if ($hasDelay) {
                    $this->processDelayedMessages($queueType, $delayedQueueKey, $queue);
                }

                // 从 Stream 中读取消息（阻塞读取）
                $messages = $this->readFromStream($queueType);

                if (!empty($messages)) {
                    foreach ($messages as $streamKey => $streamMessages) {
                        foreach ($streamMessages as $messageId => $messageData) {
                            try {
                                $data = json_decode($messageData['data'] ?? '', true);
                                if ($data !== null) {
                                    // 在消息数据中添加元信息，以便 ack/nack 使用
                                    // 为了保持兼容性，将元信息添加到数据中
                                    if (is_array($data)) {
                                        $data['_stream_meta'] = [
                                            'message_id' => $messageId,
                                            'stream_key' => $streamKey,
                                        ];
                                    }

                                    $callback($data);

                                    // 如果回调成功执行，自动确认消息
                                    $this->ackMessage($streamKey, $messageId);
                                } else {
                                    Log::warning('Invalid message data format', [
                                        'queue'      => $queue,
                                        'message_id' => $messageId,
                                    ]);
                                    // 无效消息，确认后丢弃
                                    $this->ackMessage($streamKey, $messageId);
                                }
                            } catch (Exception $e) {
                                Log::error('Message callback error', [
                                    'queue'      => $queue,
                                    'message_id' => $messageId,
                                    'error'      => $e->getMessage(),
                                ]);
                                // 消息处理失败，不确认，保持在 PENDING 列表中等待重试
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                Log::error('Queue subscribe error', [
                    'queue' => $queue,
                    'error' => $e->getMessage(),
                ]);
                sleep(1);
            }
        }
    }

    /**
     * 确保消费者组存在
     *
     * @param  ConversationQueueType  $queueType  队列类型
     *
     * @return void
     */
    protected function ensureConsumerGroup(ConversationQueueType $queueType) : void
    {
        $redis     = Redis::connection($this->connection);
        $streamKey = $queueType->getQueueName();

        try {
            // 尝试创建消费者组，如果已存在会抛出异常
            $redis->xgroup('CREATE', $streamKey, $this->consumerGroup, '0', true);
            Log::debug('Created consumer group', [
                'stream'        => $streamKey,
                'consumer_group' => $this->consumerGroup,
            ]);
        } catch (Exception $e) {
            // 消费者组已存在，忽略错误
            if (strpos($e->getMessage(), 'BUSYGROUP') === false && strpos($e->getMessage(), 'already exists') === false) {
                Log::warning('Failed to create consumer group', [
                    'stream'        => $streamKey,
                    'consumer_group' => $this->consumerGroup,
                    'error'         => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 从 Stream 中读取消息
     *
     * @param  ConversationQueueType  $queueType  队列类型
     *
     * @return array
     */
    protected function readFromStream(ConversationQueueType $queueType) : array
    {
        $redis     = Redis::connection($this->connection);
        $streamKey = $queueType->getQueueName();

        // 使用 XREADGROUP 从消费者组读取消息
        // 格式：XREADGROUP GROUP group consumer COUNT count BLOCK milliseconds STREAMS stream >
        // '>' 表示只读取未分配给其他消费者的新消息
        $messages = $redis->xreadgroup(
            $this->consumerGroup,
            $this->consumerName,
            [$streamKey => '>'],
            1, // 每次读取1条消息
            5000 // 阻塞5秒
        );

        return $messages ?: [];
    }

    /**
     * 处理延时队列中到期的消息
     *
     * @param  ConversationQueueType  $queueType  队列类型
     * @param  string  $delayedQueueKey  延时队列的 Redis key
     * @param  string  $targetStream  目标 Stream 名称
     *
     * @return void
     */
    protected function processDelayedMessages(ConversationQueueType $queueType, string $delayedQueueKey, string $targetStream) : void
    {
        try {
            $redis       = Redis::connection($this->connection);
            $currentTime = time();

            // 获取所有到期的消息（score <= 当前时间）
            $expiredMessages = $redis->zrangebyscore($delayedQueueKey, '-inf', $currentTime, ['limit' => [0, 100]]);

            if (!empty($expiredMessages)) {
                foreach ($expiredMessages as $messageJson) {
                    // 从延时队列中移除
                    $redis->zrem($delayedQueueKey, $messageJson);

                    // 推送到 Stream
                    $redis->xadd($targetStream, '*', ['data' => $messageJson]);

                    Log::debug('Moved expired delayed message to stream', [
                        'queue' => $queueType->getQueueName(),
                    ]);
                }
            }
        } catch (Exception $e) {
            Log::error('Process delayed messages error', [
                'queue' => $queueType->getQueueName(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 确认消息（内部方法）
     *
     * @param  string  $streamKey  Stream key
     * @param  string  $messageId  消息ID
     *
     * @return void
     */
    protected function ackMessage(string $streamKey, string $messageId) : void
    {
        try {
            $redis = Redis::connection($this->connection);
            $redis->xack($streamKey, $this->consumerGroup, [$messageId]);

            Log::debug('Message acknowledged', [
                'stream'     => $streamKey,
                'message_id' => $messageId,
            ]);
        } catch (Exception $e) {
            Log::error('Message ack failed', [
                'stream'     => $streamKey,
                'message_id' => $messageId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function ack(mixed $message) : void
    {
        // 兼容性方法：如果消息包含 _stream_meta，则确认消息
        // 注意：在 subscribe 中已经自动确认，此方法主要用于手动确认场景
        if (is_array($message) && isset($message['_stream_meta'])) {
            $meta = $message['_stream_meta'];
            if (isset($meta['message_id']) && isset($meta['stream_key'])) {
                $this->ackMessage($meta['stream_key'], $meta['message_id']);
            }
        } elseif (is_array($message) && isset($message['message_id']) && isset($message['stream_key'])) {
            // 直接传递元数据的情况
            $this->ackMessage($message['stream_key'], $message['message_id']);
        }
    }

    public function nack(mixed $message) : void
    {
        // Redis Stream 的 NACK 可以通过以下方式处理：
        // 1. 不确认消息，让它保持在 PENDING 列表中
        // 2. 使用 XCLAIM 将消息重新分配给其他消费者
        // 3. 记录到死信队列

        $streamKey  = null;
        $messageId  = null;

        if (is_array($message)) {
            if (isset($message['_stream_meta'])) {
                $meta       = $message['_stream_meta'];
                $streamKey  = $meta['stream_key'] ?? null;
                $messageId  = $meta['message_id'] ?? null;
            } elseif (isset($message['message_id']) && isset($message['stream_key'])) {
                $streamKey = $message['stream_key'];
                $messageId = $message['message_id'];
            }
        }

        if ($streamKey && $messageId) {
            try {
                // 方案1：记录日志，消息保持在 PENDING 列表中，可以通过 XCLAIM 重新处理
                Log::warning('Message nacked, will remain in pending list', [
                    'stream'     => $streamKey,
                    'message_id' => $messageId,
                ]);

                // 可选：将消息推送到死信队列
                // $this->publishToDeadLetterQueue($message);
            } catch (Exception $e) {
                Log::error('Message nack failed', [
                    'stream'     => $streamKey,
                    'message_id' => $messageId,
                    'error'     => $e->getMessage(),
                ]);
            }
        } else {
            Log::warning('Message nacked (no stream metadata)', ['message' => $message]);
        }
    }


    /**
     * 获取会话中所有未处理的消息
     *
     * @param  string  $conversationId  会话ID
     * @param  float|null  $maxScore  最大分数（用于获取指定时间之前的消息）
     *
     * @return array 消息数组
     * @throws Exception
     */
    public function getConversationMessages(string $conversationId, float $maxScore = null) : array
    {
        $key = "conversation:messages:{$conversationId}";

        try {
            if ($maxScore !== null) {
                $messages = Redis::connection($this->connection)->zrangebyscore($key, '-inf', $maxScore);
            } else {
                $messages = Redis::connection($this->connection)->zrange($key, 0, -1);
            }

            $result = [];
            foreach ($messages as $message) {
                $decoded  = json_decode($message, true);
                $result[] = $decoded !== null ? $decoded : $message;
            }

            return $result;
        } catch (Exception $e) {
            Log::error('Get conversation messages failed', [
                'conversation_id' => $conversationId,
                'error'           => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * 从会话ZSET中移除消息
     *
     * @param  string  $conversationId  会话ID
     * @param  float  $maxScore  最大分数（移除指定时间之前的消息）
     *
     * @return int 移除的消息数量
     */
    public function removeConversationMessages(string $conversationId, float $maxScore) : int
    {
        $key = "conversation:{$conversationId}:messages";

        try {
            return Redis::connection($this->connection)->zremrangebyscore($key, '-inf', $maxScore);
        } catch (Exception $e) {
            Log::error('Remove conversation messages failed', [
                'conversation_id' => $conversationId,
                'error'           => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
