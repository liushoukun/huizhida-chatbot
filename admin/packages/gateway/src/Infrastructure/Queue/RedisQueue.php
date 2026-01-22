<?php

namespace HuiZhiDa\Gateway\Infrastructure\Queue;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Gateway\Domain\Contracts\MessageQueueInterface;

class RedisQueue implements MessageQueueInterface
{
    protected string $connection;
    protected array $subscribers = [];

    public function __construct(array $config = [])
    {
        $this->connection = $config['connection'] ?? 'default';
    }

    public function publish(string $queue, mixed $message): void
    {
        $data = is_string($message) ? $message : json_encode($message);
        
        try {
            Redis::connection($this->connection)->lpush($queue, $data);
        } catch (\Exception $e) {
            Log::error('Queue publish failed', [
                'queue' => $queue,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function subscribe(string $queue, callable $callback): void
    {
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
            } catch (\Exception $e) {
                Log::error('Queue subscribe error', [
                    'queue' => $queue,
                    'error' => $e->getMessage(),
                ]);
                sleep(1);
            }
        }
    }

    public function ack(mixed $message): void
    {
        // Redis 列表队列不需要 ACK，消息被消费后自动删除
    }

    public function nack(mixed $message): void
    {
        // Redis 列表队列不支持 NACK
        // 可以记录日志或推送到失败队列
        Log::warning('Message nacked', ['message' => $message]);
    }
}
