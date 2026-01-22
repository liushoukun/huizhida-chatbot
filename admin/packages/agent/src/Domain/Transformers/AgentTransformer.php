<?php

namespace HuiZhiDa\Agent\Domain\Transformers;

use HuiZhiDa\Agent\Domain\Data\AgentData;
use HuiZhiDa\Agent\Domain\Models\Agent;
use RedJasmine\Support\Domain\Transformer\TransformerInterface;

class AgentTransformer implements TransformerInterface
{
    public function transform($data, $model): Agent
    {
        if (!$data instanceof AgentData) {
            throw new \InvalidArgumentException('Data must be an instance of AgentData');
        }

        if (!$model instanceof Agent) {
            throw new \InvalidArgumentException('Model must be an instance of Agent');
        }

        $model->name = $data->name;
        $model->agent_type = $data->agentType;
        $model->provider = $data->provider;
        $model->config = $data->config;
        $model->fallback_agent_id = $data->fallbackAgentId;
        $model->status = $data->status;

        // 设置所有者
        if (isset($data->owner)) {
            $model->owner_type = $data->owner->getType();
            $model->owner_id = $data->owner->getID();
        }

        return $model;
    }
}
