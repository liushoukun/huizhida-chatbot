<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO;

use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationStatus;
use RedJasmine\Support\Domain\Contracts\UserInterface;
use RedJasmine\Support\Domain\Models\Casts\UserInterfaceCastTransformer;
use RedJasmine\Support\Foundation\Data\Data;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;

class ConversationData extends Data
{

    public string $conversationId;

    /**
     * @var ConversationStatus
     */
    #[WithCast(EnumCast::class, ConversationStatus::class)]
    public ConversationStatus $status = ConversationStatus::Pending;


    public int $channelId;


    public int $appId;


    /**
     * @var string|null
     */
    public ?string $agentConversationId = null;

    public ?string $channelConversationId = null;


    public ?string $channelAppId = null;

    /**
     * @var UserInterface|null
     */
    #[WithCast(UserInterfaceCastTransformer::class)]
    public ?UserInterface $user = null;

}