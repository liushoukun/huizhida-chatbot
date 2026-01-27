<?php

namespace HuiZhiDa\Processor\Infrastructure\Adapters;

use HuiZhiDa\Processor\Domain\Contracts\AgentAdapterInterface;
use HuiZhiDa\Core\Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * 智能体适配器工厂
 */
class AgentAdapterFactory
{
    /**
     * 创建智能体适配器
     *
     * @param  Agent  $agent
     *
     * @return AgentAdapterInterface
     */
    public function create(Agent $agent) : AgentAdapterInterface
    {
        $agentType = $agent->agent_type;
        $config    = $agent->config ?? [];
        return match ($agent->agent_type) {
            'coze' => new CozeAdapter($config),
            default => throw new InvalidArgumentException("Unsupported provider: {$agentType}. Supported: coze, tencent_yuanqi"),
        };

    }


}
