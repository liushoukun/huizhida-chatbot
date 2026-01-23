<?php

namespace HuiZhiDa\Message\Domain\DTO;

use RedJasmine\Support\Foundation\Data\Data;

/**
 *
 */
class ConversationEvent extends Data
{

    public static string $processing = 'processing';


    public function __construct(string $conversationId)
    {
        $this->conversationId = $conversationId;
        $this->timestamp      = time();
    }


    public string $event = 'processing';

    public string $conversationId;

    public int $timestamp;


    public ?int $channelId = null;

    public ?int $agentId = null;

}