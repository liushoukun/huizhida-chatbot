<?php

namespace HuiZhiDa\Core\Domain\Channel\Transformers;

use HuiZhiDa\Core\Domain\Channel\Data\ChannelData;
use HuiZhiDa\Core\Domain\Channel\Models\Channel;
use RedJasmine\Support\Domain\Transformer\TransformerInterface;

class ChannelTransformer implements TransformerInterface
{
    public function transform($data, $model): Channel
    {
        if (!$data instanceof ChannelData) {
            throw new \InvalidArgumentException('Data must be an instance of ChannelData');
        }

        if (!$model instanceof Channel) {
            throw new \InvalidArgumentException('Model must be an instance of Channel');
        }

        $model->app_id = $data->appId;
        $model->agent_id = $data->agentId;
        $model->channel = $data->channel;
        $model->config = $data->config;
        $model->status = $data->status;

        return $model;
    }
}
