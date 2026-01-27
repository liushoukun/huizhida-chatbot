<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO;

use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationStatus;
use RedJasmine\Support\Domain\Contracts\UserInterface;
use RedJasmine\Support\Domain\Models\Casts\UserInterfaceCastTransformer;
use RedJasmine\Support\Foundation\Data\Data;
use RedJasmine\Support\Helpers\ID\DatetimeIdGenerator;
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

    /**
     * 渠道ID
     * @var int
     */
    public int $channelId;


    public int $appId;


    /**
     * @var string|null
     */
    public ?string $agentConversationId = null;
    /**
     * 渠道应用ID
     * @var string|null
     */
    public ?string $channelAppId = null;
    /**
     * 渠道会话ID
     * @var string|null
     */
    public ?string $channelConversationId = null;


    /**
     * @var UserInterface|null
     */
    #[WithCast(UserInterfaceCastTransformer::class)]
    public ?UserInterface $user = null;


    public function getConversationId() : string
    {
        if (!$this->conversationId) {
            $this->conversationId = static::buildID();
        }
        return $this->conversationId;
    }


    public static function buildID() : string
    {
        return DatetimeIdGenerator::buildId();
    }
}