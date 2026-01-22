<?php

namespace HuiZhiDa\AgentProcessor\Infrastructure\Adapters;

use HuiZhiDa\AgentProcessor\Domain\Contracts\AgentAdapterInterface;
use HuiZhiDa\Agent\Domain\Models\Agent;
use Illuminate\Support\Facades\Log;

/**
 * 智能体适配器工厂
 */
class AgentAdapterFactory
{
    /**
     * 创建智能体适配器
     *
     * @param Agent $agent
     * @return AgentAdapterInterface
     */
    public function create(Agent $agent): AgentAdapterInterface
    {
        $agentType = $agent->agent_type;
        $config = $agent->config ?? [];

        // 只支持远程智能体类型
        if ($agentType !== 'remote') {
            throw new \InvalidArgumentException("Only 'remote' agent type is supported. Got: {$agentType}");
        }

        return $this->createRemoteAdapter($config);
    }

    /**
     * 创建远程智能体适配器
     */
    protected function createRemoteAdapter(array $config): AgentAdapterInterface
    {
        $provider = $config['provider'] ?? '';
        
        if (empty($provider)) {
            throw new \InvalidArgumentException("Provider is required in agent config");
        }
        
        return match ($provider) {
            'coze' => new CozeAdapter($config),
            'tencent_yuanqi' => new TencentYuanqiAdapter($config),
            default => throw new \InvalidArgumentException("Unsupported provider: {$provider}. Supported: coze, tencent_yuanqi"),
        };
    }
}
