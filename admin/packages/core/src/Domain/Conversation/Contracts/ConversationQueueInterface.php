<?php

namespace HuiZhiDa\Core\Domain\Conversation\Contracts;

use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use RedJasmine\Support\Foundation\Data\Data;

interface ConversationQueueInterface
{
    /**
     * 发布会话MQ
     */
    public function publish(ConversationQueueType $queueType, Data $message) : void;

    /**
     * 订阅队列消息
     */
    public function subscribe(ConversationQueueType $queueType, callable $callback) : void;

    /**
     * 确认消息
     */
    public function ack(mixed $message) : void;

    /**
     * 拒绝消息
     */
    public function nack(mixed $message) : void;
}
