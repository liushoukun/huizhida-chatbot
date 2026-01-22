<?php

namespace HuiZhiDa\Channel\Application\Services;

use HuiZhiDa\Channel\Domain\Models\Channel;
use HuiZhiDa\Channel\Domain\Repositories\ChannelRepositoryInterface;
use HuiZhiDa\Channel\Domain\Transformers\ChannelTransformer;
use RedJasmine\Support\Application\ApplicationService;

class ChannelApplicationService extends ApplicationService
{
    public static string $hookNamePrefix = 'channel.application';
    protected static string $modelClass = Channel::class;

    public function __construct(
        public ChannelRepositoryInterface $repository,
        public ChannelTransformer $transformer
    ) {
    }
}
