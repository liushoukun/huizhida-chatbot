<?php

namespace HuiZhiDa\Core\Domain\Agent\Contracts;

use RedJasmine\Support\Domain\Contracts\TypeEnumInterface;

/**
 * 智能体类型接口
 */
interface AgentTypeInterface extends TypeEnumInterface
{
    /**
     * 获取配置字段集合
     * 返回 Filament 表单组件数组，用于动态显示配置表单
     *
     * @return array<\Filament\Forms\Components\Component>
     */
    public function getConfigFields(): array;
}
