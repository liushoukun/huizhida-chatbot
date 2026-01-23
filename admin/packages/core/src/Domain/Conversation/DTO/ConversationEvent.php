<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO;

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


    public ConversationQueueType $queue = ConversationQueueType::Processor;

    public string $conversationId;

    public int $timestamp;


    public ?int $channelId = null;

    public ?int $agentId = null;

}
