<?php

namespace HuiZhiDa\Core\Domain\Agent\Strategies\AgentType;

use HuiZhiDa\Core\Domain\Agent\Contracts\AgentTypeInterface;
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

        'coze' => CozeAgentType::class,
    ];

}
