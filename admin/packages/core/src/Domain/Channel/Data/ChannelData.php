<?php

namespace HuiZhiDa\Core\Domain\Channel\Data;

use HuiZhiDa\Core\Domain\Channel\Models\Enums\ChannelStatus;
use HuiZhiDa\Core\Domain\Channel\Models\Enums\ChannelType;
use RedJasmine\Support\Foundation\Data\Data;
use RedJasmine\Support\Helpers\Enums\EnumCast;

class ChannelData extends Data
{
    public int $appId;
    public ?int $agentId = null;

    #[EnumCast(ChannelType::class)]
    public ChannelType $channel;
    
    public ?array $config = null;
    
    #[EnumCast(ChannelStatus::class)]
    public ChannelStatus $status = ChannelStatus::ENABLED;
}
