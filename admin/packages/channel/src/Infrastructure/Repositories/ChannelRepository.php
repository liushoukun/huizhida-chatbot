<?php

namespace HuiZhiDa\Channel\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use HuiZhiDa\Channel\Domain\Models\Channel;
use HuiZhiDa\Channel\Domain\Models\Enums\ChannelStatus;
use HuiZhiDa\Channel\Domain\Models\Enums\ChannelType;
use HuiZhiDa\Channel\Domain\Repositories\ChannelRepositoryInterface;
use RedJasmine\Support\Domain\Queries\Query;
use RedJasmine\Support\Infrastructure\Repositories\Repository;

class ChannelRepository extends Repository implements ChannelRepositoryInterface
{
    protected static string $modelClass = Channel::class;

    public function findByAppId(string $appId): Collection
    {
        return Channel::where('app_id', $appId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByChannelType(ChannelType $channelType): Collection
    {
        return Channel::where('channel', $channelType)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByAppIdAndChannelType(string $appId, ChannelType $channelType): ?Channel
    {
        return Channel::where('app_id', $appId)
            ->where('channel', $channelType)
            ->first();
    }

    public function findEnabled(): Collection
    {
        return Channel::where('status', ChannelStatus::ENABLED)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findEnabledByAppId(string $appId): Collection
    {
        return Channel::where('app_id', $appId)
            ->where('status', ChannelStatus::ENABLED)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function channelTypeExistsForApp(string $appId, ChannelType $channelType, ?string $excludeId = null): bool
    {
        $query = Channel::where('app_id', $appId)
            ->where('channel', $channelType);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    protected function allowedFilters(?Query $query = null): array
    {
        return [
            'app_id',
            'channel',
            'status',
        ];
    }

    protected function allowedSorts(?Query $query = null): array
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

    protected function allowedIncludes(?Query $query = null): array
    {
        return [];
    }
}
