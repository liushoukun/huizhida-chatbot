<?php

namespace HuiZhiDa\Engine\Core\Infrastructure\Queue;

use Exception;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use RedJasmine\Support\Foundation\Data\Data;

class RabbitMQQueue implements ConversationQueueInterface
{
    protected ?AMQPStreamConnection $connection = null;
    protected ?AMQPChannel $channel = null;
    protected array $config;
    protected array $subscribers = [];

    // RabbitMQ 的延时队列交换机和队列名称
    protected const DELAYED_EXCHANGE = 'conversations.delayed';
    protected const DEAD_LETTER_EXCHANGE = 'conversations.dlx';

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'host' => env('RABBITMQ_HOST', 'localhost'),
            'port' => env('RABBITMQ_PORT', 5672),
            'user' => env('RABBITMQ_USER', 'guest'),
            'password' => env('RABBITMQ_PASSWORD', 'guest'),
            'vhost' => env('RABBITMQ_VHOST', '/'),
            'heartbeat' => 60,
            'connection_timeout' => 3,
            'read_write_timeout' => 3,
        ], $config);
    }

    /**
     * 获取或创建 RabbitMQ 连接
     *
     * @return AMQPStreamConnection
     * @throws Exception
     */
    protected function getConnection(): AMQPStreamConnection
    {
        if ($this->connection === null || !$this->connection->isConnected()) {
            try {
                $this->connection = new AMQPStreamConnection(
                    $this->config['host'],
                    $this->config['port'],
                    $this->config['user'],
                    $this->config['password'],
                    $this->config['vhost'],
                    false,
                    'AMQPLAIN',
                    null,
                    'en_US',
                    $this->config['connection_timeout'],
                    $this->config['read_write_timeout'],
                    null,
                    false,
                    $this->config['heartbeat']
                );

                Log::info('RabbitMQ connection established', [
                    'host' => $this->config['host'],
                    'port' => $this->config['port'],
                ]);
            } catch (Exception $e) {
                Log::error('Failed to connect to RabbitMQ', [
                    'error' => $e->getMessage(),
                    'config' => array_except($this->config, ['password']),
                ]);
                throw $e;
            }
        }

        return $this->connection;
    }

    /**
     * 获取或创建 RabbitMQ 通道
     *
     * @return AMQPChannel
     * @throws Exception
     */
    protected function getChannel(): AMQPChannel
    {
        if ($this->channel === null || !$this->channel->is_open()) {
            $connection = $this->getConnection();
            $this->channel = $connection->channel();

            // 设置 QoS，每次只预取一条消息，保证负载均衡
            $this->channel->basic_qos(0, 1, false);
        }

        return $this->channel;
    }

    /**
     * 声明队列和交换机
     *
     * @param ConversationQueueType $queueType
     * @return void
     * @throws Exception
     */
    protected function declareQueue(ConversationQueueType $queueType): void
    {
        $channel = $this->getChannel();
        $queueName = $queueType->getQueueName();
        $delaySeconds = $queueType->getDelaySeconds();

        // 声明主队列（durable=true 持久化）
        $channel->queue_declare(
            $queueName,
            false,  // passive
            true,   // durable - 队列持久化
            false,  // exclusive
            false   // auto_delete
        );

        // 如果需要延时队列，设置死信交换机机制
        if ($delaySeconds !== null) {
            $delayedQueueName = "{$queueName}.delayed";

            // 声明死信交换机
            $channel->exchange_declare(
                self::DEAD_LETTER_EXCHANGE,
                'direct',
                false,  // passive
                true,   // durable
                false   // auto_delete
            );

            // 声明延时队列，配置死信交换机
            $args = new AMQPTable([
                'x-dead-letter-exchange' => self::DEAD_LETTER_EXCHANGE,
                'x-dead-letter-routing-key' => $queueName,
            ]);

            $channel->queue_declare(
                $delayedQueueName,
                false,  // passive
                true,   // durable
                false,  // exclusive
                false,  // auto_delete
                false,  // nowait
                $args   // arguments
            );

            // 将死信交换机绑定到主队列
            $channel->queue_bind($queueName, self::DEAD_LETTER_EXCHANGE, $queueName);

            Log::debug('Declared delayed queue', [
                'queue' => $queueName,
                'delayed_queue' => $delayedQueueName,
                'delay_seconds' => $delaySeconds,
            ]);
        }
    }

    /**
     * 判断当前事件是否为会话的最后一个事件
     * 使用 Laravel Cache 代替 Redis 直接操作，以支持多种缓存驱动
     *
     * @param ConversationEvent $event
     * @return bool
     */
    public function isLastEvent(ConversationEvent $event): bool
    {
        $key = "conversation:last_event:{$event->queue->getQueueName()}:{$event->conversationId}";
        $lastEventId = Cache::get($key);
        return ($lastEventId === $event->id) || !$lastEventId;
    }

    /**
     * 记录最后一个事件ID，用于防抖处理
     *
     * @param ConversationEvent $event
     * @return void
     */
    public function recordLastEvent(ConversationEvent $event): void
    {
        $key = "conversation:last_event:{$event->queue->getQueueName()}:{$event->conversationId}";
        Cache::put($key, $event->id, 60 * 60 * 24); // 缓存24小时
    }

    /**
     * 发布消息到 RabbitMQ 队列
     *
     * @param  ConversationQueueType  $queueType
     * @param  Data  $message
     * @param  int|null  $delaySeconds
     *
     * @return void
     * @throws Exception
     */
    public function publish(ConversationQueueType $queueType, Data $message, ?int $delaySeconds = null): void
    {
        try {
            $this->declareQueue($queueType);

            $queueName = $queueType->getQueueName();

            $messageBody = $message->toJson();

            // 创建消息属性
            $messageProperties = [
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT, // 消息持久化
                'content_type' => 'application/json',
            ];

            // 如果配置了延时，发送到延时队列
            if ($delaySeconds !== null) {
                $delayedQueueName = "{$queueName}.delayed";

                // 设置消息过期时间（TTL）
                $messageProperties['expiration'] = strval($delaySeconds * 1000); // 毫秒

                $amqpMessage = new AMQPMessage($messageBody, $messageProperties);

                // 发布到延时队列
                $this->getChannel()->basic_publish($amqpMessage, '', $delayedQueueName);

                Log::debug('Published message to delayed queue', [
                    'queue' => $queueName,
                    'delayed_queue' => $delayedQueueName,
                    'delay_seconds' => $delaySeconds,
                ]);
            } else {
                // 立即发布到主队列
                $amqpMessage = new AMQPMessage($messageBody, $messageProperties);
                $this->getChannel()->basic_publish($amqpMessage, '', $queueName);

                Log::debug('Published message to queue', [
                    'queue' => $queueName,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Queue publish failed', [
                'queue' => $queueType->getQueueName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 重新抛出异常，让上层处理
            throw $e;
        }
    }

    /**
     * 订阅队列消息
     *
     * @param ConversationQueueType $queueType
     * @param callable $callback
     * @return void
     * @throws Exception
     */
    public function subscribe(ConversationQueueType $queueType, callable $callback): void
    {
        $queueName = $queueType->getQueueName();
        $this->subscribers[$queueName] = $callback;

        try {
            $this->declareQueue($queueType);
            $channel = $this->getChannel();

            Log::info('Starting to consume messages from queue', [
                'queue' => $queueName,
            ]);

            // 定义消息处理回调
            $messageCallback = function (AMQPMessage $amqpMessage) use ($callback, $queueName) {
                try {
                    $messageBody = $amqpMessage->getBody();
                    $messageData = json_decode($messageBody, true);

                    if ($messageData === null) {
                        Log::warning('Failed to decode message', [
                            'queue' => $queueName,
                            'body' => $messageBody,
                        ]);
                        // 拒绝无效消息，不重新入队
                        $amqpMessage->nack(false);
                        return;
                    }

                    Log::debug('Processing message', [
                        'queue' => $queueName,
                        'message' => $messageData,
                    ]);

                    // 执行用户回调
                    $callback($messageData);

                    // 手动确认消息
                    $amqpMessage->ack();

                    Log::debug('Message processed successfully', [
                        'queue' => $queueName,
                    ]);
                } catch (Exception $e) {
                    Log::error('Message processing failed', [
                        'queue' => $queueName,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // 拒绝消息并重新入队（可以配置重试次数）
                    $amqpMessage->nack(true);
                }
            };

            // 开始消费消息
            $channel->basic_consume(
                $queueName,
                '',     // consumer_tag
                false,  // no_local
                false,  // no_ack - 手动确认
                false,  // exclusive
                false,  // nowait
                $messageCallback
            );

            // 持续监听消息
            while ($channel->is_consuming()) {
                $channel->wait();
            }
        } catch (Exception $e) {
            Log::error('Queue subscribe error', [
                'queue' => $queueName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 尝试重新连接
            $this->reconnect();
            throw $e;
        }
    }

    /**
     * 确认消息（RabbitMQ 在消费回调中自动处理）
     *
     * @param mixed $message
     * @return void
     */
    public function ack(mixed $message): void
    {
        // 在 RabbitMQ 中，ACK 是在消费回调中通过 AMQPMessage::ack() 处理的
        // 这里保留接口实现，可以用于记录日志
        Log::debug('Message acknowledged', [
            'message' => $message,
        ]);
    }

    /**
     * 拒绝消息（RabbitMQ 在消费回调中自动处理）
     *
     * @param mixed $message
     * @return void
     */
    public function nack(mixed $message): void
    {
        // 在 RabbitMQ 中，NACK 是在消费回调中通过 AMQPMessage::nack() 处理的
        // 这里保留接口实现，可以用于记录日志
        Log::warning('Message nacked', [
            'message' => $message,
        ]);
    }

    /**
     * 重新连接 RabbitMQ
     *
     * @return void
     */
    protected function reconnect(): void
    {
        try {
            $this->close();
            sleep(1);
            $this->getConnection();
            Log::info('RabbitMQ reconnected successfully');
        } catch (Exception $e) {
            Log::error('RabbitMQ reconnection failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 关闭连接和通道
     *
     * @return void
     */
    public function close(): void
    {
        try {
            if ($this->channel !== null && $this->channel->is_open()) {
                $this->channel->close();
                $this->channel = null;
            }

            if ($this->connection !== null && $this->connection->isConnected()) {
                $this->connection->close();
                $this->connection = null;
            }

            Log::debug('RabbitMQ connection closed');
        } catch (Exception $e) {
            Log::error('Failed to close RabbitMQ connection', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 析构函数，确保连接被关闭
     */
    public function __destruct()
    {
        $this->close();
    }
}
