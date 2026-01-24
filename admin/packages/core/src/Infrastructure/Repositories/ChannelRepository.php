<?php

namespace HuiZhiDa\Core\Infrastructure\Repositories;

use HuiZhiDa\Core\Domain\Channel\Models\Channel;
use HuiZhiDa\Core\Domain\Channel\Models\Enums\ChannelStatus;
use HuiZhiDa\Core\Domain\Channel\Repositories\ChannelRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use RedJasmine\Support\Domain\Queries\Query;
use RedJasmine\Support\Infrastructure\Repositories\Repository;

class ChannelRepository extends Repository implements ChannelRepositoryInterface
{
    protected static string $modelClass = Channel::class;

    public function findByAppId(string $appId) : Collection
    {
        return Channel::where('app_id', $appId)
                      ->orderBy('created_at', 'desc')
                      ->get();
    }


    public function findEnabled() : Collection
    {
        return Channel::where('status', ChannelStatus::ENABLED)
                      ->orderBy('created_at', 'desc')
                      ->get();
    }

    public function findEnabledByAppId(string $appId) : Collection
    {
        return Channel::where('app_id', $appId)
                      ->where('status', ChannelStatus::ENABLED)
                      ->orderBy('created_at', 'desc')
                      ->get();
    }


    protected function allowedFilters(?Query $query = null) : array
    {
        return [
            'app_id',
            'channel',
            'status',
        ];
    }

    protected function allowedSorts(?Query $query = null) : array
    {
        return [
            'id',
            'app_id',
            'channel',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    protected function allowedIncludes(?Query $query = null) : array
    {
        return [];
    }
}
