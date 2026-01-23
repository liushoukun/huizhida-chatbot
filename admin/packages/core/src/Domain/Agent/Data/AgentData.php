<?php

namespace HuiZhiDa\Core\Domain\Agent\Data;

use HuiZhiDa\Core\Domain\Agent\Models\Enums\AgentStatus;
use RedJasmine\Support\Domain\Contracts\UserInterface;
use RedJasmine\Support\Foundation\Data\Data;
use RedJasmine\Support\Helpers\Enums\EnumCast;

class AgentData extends Data
{
    public UserInterface $owner;
    public string        $name;

    public string $agentType;

    public ?string $provider        = null;
    public ?array  $config          = null;
    public ?string $fallbackAgentId = null;

    #[EnumCast(AgentStatus::class)]
    public AgentStatus $status = AgentStatus::ENABLED;
}
