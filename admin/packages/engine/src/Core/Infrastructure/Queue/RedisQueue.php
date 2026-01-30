<?php

namespace HuiZhiDa\Engine\Core\Infrastructure\Queue;

use Exception;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RedJasmine\Support\Foundation\Data\Data;

class RedisQueue implements ConversationQueueInterface
{
    protected string $connection;
    protected array  $subscribers = [];


    public function __construct(array $config = [])
    {
        $this->connection = $config['connection'] ?? 'default';

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


    public function publish(ConversationQueueType $queueType, Data $message, ?int $delaySeconds = null) : void
    {
        $data = $message->toJson();
        try {


            // 如果配置了延时队列，使用延时队列
            if ($delaySeconds !== null) {
                $this->publishDelayed($queueType, $message, $delaySeconds);
            } else {
                // 立即发布到普通队列
                Redis::connection($this->connection)->lpush($queueType->getQueueName(), $data);
            }
        } catch (Exception $e) {
            Log::error('Queue publish failed', [
                'queue' => $queueType->getQueueName(),
                'error' => $e->getMessage(),
            ]);

        }
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
        $redis = Redis::connection($this->connection);
        $delayedQueueKey = "{$queueType->getQueueName()}:delayed";
        $executeTime = time() + $delaySeconds; // 执行时间戳
        $data = $message->toJson();

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
        $queue                     = $queueType->getQueueName();
        $this->subscribers[$queue] = $callback;
        $delayedQueueKey           = "{$queue}:delayed";
        $hasDelay                  = $queueType->getDelaySeconds() !== null;

        // 在 Laravel 中，通常使用队列 worker 来处理
        // 这里提供一个简单的阻塞订阅实现
        while (true) {
            try {
                // 如果配置了延时队列，先处理到期的延时消息
                if ($hasDelay) {
                    $this->processDelayedMessages($queueType, $delayedQueueKey, $queue);
                }

                // 从普通队列中取消息
                $data = Redis::connection($this->connection)->brpop($queue, 5);

                if ($data && isset($data[1])) {
                    $message = json_decode($data[1], true);
                    if ($message !== null) {
                        $callback($message);
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
     * 处理延时队列中到期的消息
     *
     * @param  ConversationQueueType  $queueType  队列类型
     * @param  string  $delayedQueueKey  延时队列的 Redis key
     * @param  string  $targetQueue  目标队列名称
     *
     * @return void
     */
    protected function processDelayedMessages(ConversationQueueType $queueType, string $delayedQueueKey, string $targetQueue) : void
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

                    // 推送到普通队列
                    $redis->lpush($targetQueue, $messageJson);

                    Log::debug('Moved expired delayed message to queue', [
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

    public function ack(mixed $message) : void
    {
        // Redis 列表队列不需要 ACK，消息被消费后自动删除
    }

    public function nack(mixed $message) : void
    {
        // Redis 列表队列不支持 NACK
        // 可以记录日志或推送到失败队列
        Log::warning('Message nacked', ['message' => $message]);
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
