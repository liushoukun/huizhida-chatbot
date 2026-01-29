<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO\Contents;

use HuiZhiDa\Core\Domain\Conversation\Enums\EventType;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;

class EventContent extends Content
{

    /**
     * @var EventType
     */
    #[WithCast(EnumCast::class, EventType::class)]
    public EventType $event;

    /**
     * 接待人员
     * @var string|null
     */
    public ?string $servicer = null;
}