<?php

namespace HuiZhiDa\Agent\Domain\Data;

use HuiZhiDa\Agent\Domain\Models\Enums\AgentStatus;
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
