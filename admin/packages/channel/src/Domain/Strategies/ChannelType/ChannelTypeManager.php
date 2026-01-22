<?php

namespace HuiZhiDa\Channel\Domain\Strategies\ChannelType;

use HuiZhiDa\Channel\Domain\Contracts\ChannelTypeInterface;
use RedJasmine\Support\Foundation\Manager\EnumManager;

/**
 * 渠道类型管理器
 */
class ChannelTypeManager extends EnumManager
{
    /**
     * @var array<string, class-string<ChannelTypeInterface>>
     */
    protected const DRIVERS = [
        'wecom' => WecomChannelType::class,
        'api' => ApiChannelType::class,
    ];

}
