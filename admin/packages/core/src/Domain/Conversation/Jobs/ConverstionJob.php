<?php

namespace HuiZhiDa\Core\Domain\Conversation\Jobs;

use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ConverstionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ConversationEvent $conversationEvent)
    {
    }

    public function handle() : void
    {
    }
}
