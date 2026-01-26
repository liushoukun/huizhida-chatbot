<?php

namespace HuiZhiDa\Core\Application\Services;

use HuiZhiDa\Core\Domain\Channel\Models\Channel;
use HuiZhiDa\Core\Domain\Channel\Repositories\ChannelRepositoryInterface;
use HuiZhiDa\Core\Domain\Channel\Transformers\ChannelTransformer;
use RedJasmine\Support\Application\ApplicationService;
use RedJasmine\Support\Domain\Queries\FindQuery;

/**
 * @method Channel find(FindQuery $query)
 */
class ChannelApplicationService extends ApplicationService
{
    public static string    $hookNamePrefix = 'channel.application';
    protected static string $modelClass     = Channel::class;

    public function __construct(
        public ChannelRepositoryInterface $repository,
        public ChannelTransformer $transformer
    ) {
    }


    public function findByChannelId(int $channelId) : Channel
    {
        return $this->repository->find($channelId);
    }
}
