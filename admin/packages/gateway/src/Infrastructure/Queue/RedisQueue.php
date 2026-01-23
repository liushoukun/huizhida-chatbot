<?php

namespace HuiZhiDa\Gateway\Infrastructure\Queue;

use Exception;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use HuiZhiDa\Core\Domain\Conversation\Services\CommonService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use RedJasmine\Support\Foundation\Data\Data;

class RedisQueue implements ConversationQueueInterface
{
    protected string        $connection;
    protected array         $subscribers = [];
    protected CommonService $commonService;

    public function __construct(array $config = [])
    {
        $this->connection    = $config['connection'] ?? 'default';
        $this->commonService = app(CommonService::class);
    }

    public function publish(ConversationQueueType $queueType, Data $message) : void
    {
        $data = $message->toJson();

        try {

            Redis::connection($this->connection)->lpush($queueType->getQueueName(), $data);
        } catch (Exception $e) {
            Log::error('Queue publish failed', [
                'queue' => $queueType->getQueueName(),
                'error' => $e->getMessage(),
            ]);

        }
    }

    public function subscribe(ConversationQueueType $queueType, callable $callback) : void
    {
        $queue                     = $queueType->getQueueName();
        $this->subscribers[$queue] = $callback;

        // 在 Laravel 中，通常使用队列 worker 来处理
        // 这里提供一个简单的阻塞订阅实现
        while (true) {
            try {
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
