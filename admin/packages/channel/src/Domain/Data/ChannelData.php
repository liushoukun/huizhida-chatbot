<?php

namespace HuiZhiDa\Channel\Domain\Data;

use HuiZhiDa\Channel\Domain\Models\Enums\ChannelStatus;
use HuiZhiDa\Channel\Domain\Models\Enums\ChannelType;
use RedJasmine\Support\Foundation\Data\Data;
use RedJasmine\Support\Helpers\Enums\EnumCast;

class ChannelData extends Data
{
    public int $appId;
    
    #[EnumCast(ChannelType::class)]
    public ChannelType $channel;
    
    public ?array $config = null;
    
    #[EnumCast(ChannelStatus::class)]
    public ChannelStatus $status = ChannelStatus::ENABLED;
}
