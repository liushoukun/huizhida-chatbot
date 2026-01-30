<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO\Events;

use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use Illuminate\Support\Str;
use RedJasmine\Support\Foundation\Data\Data;

/**
 *
 */
class ConversationEvent extends Data
{

    public string $id;


    public function __construct(string $conversationId)
    {
        $this->conversationId = $conversationId;
        $this->timestamp      = time();
        $this->id             = Str::uuid();
    }


    public ConversationQueueType $queue = ConversationQueueType::Inputs;

    public string $conversationId;

    public int $timestamp;


    protected ?int $delaySeconds;

    protected bool $hasDelaySeconds = false;

    public function setDelaySeconds(?int $delaySeconds = null) : static
    {
        $this->delaySeconds    = $delaySeconds;
        $this->hasDelaySeconds = true;
        return $this;
    }


    public function getDelaySeconds() : ?int
    {
        // 如果显式设置了延时秒数（包括设置为 null），则返回设置的值，否则从队列类型获取默认延时
        return $this->hasDelaySeconds ? $this->delaySeconds : $this->queue->getDelaySeconds();
    }

}
