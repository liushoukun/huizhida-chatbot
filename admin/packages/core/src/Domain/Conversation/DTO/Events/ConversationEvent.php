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

}
