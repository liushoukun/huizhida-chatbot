<?php

namespace HuiZhiDa\Agent\Domain\Strategies\AgentType;

use HuiZhiDa\Agent\Domain\Contracts\AgentTypeInterface;
use RedJasmine\Support\Foundation\Manager\EnumManager;

/**
 * 智能体类型管理器
 */
class AgentTypeManager extends EnumManager
{
    /**
     * @var array<string, class-string<AgentTypeInterface>>
     */
    protected const DRIVERS = [
        'tencent_yuanqi' => TencentYuanqiAgentType::class,
    ];

}
