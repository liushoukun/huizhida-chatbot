<?php

namespace HuiZhiDa\Message\Domain\Contracts;

interface MessageQueueInterface
{
    /**
     * 发布消息到队列
     */
    public function publish(string $queue, mixed $message): void;

    /**
     * 订阅队列消息
     */
    public function subscribe(string $queue, callable $callback): void;

    /**
     * 确认消息
     */
    public function ack(mixed $message): void;

    /**
     * 拒绝消息
     */
    public function nack(mixed $message): void;
}
