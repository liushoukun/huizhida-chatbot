<?php

namespace HuiZhiDa\Channel\Domain\Repositories;

use Illuminate\Database\Eloquent\Collection;
use HuiZhiDa\Channel\Domain\Models\Channel;
use HuiZhiDa\Channel\Domain\Models\Enums\ChannelType;
use Illuminate\Database\Eloquent\Model;
use RedJasmine\Support\Domain\Repositories\RepositoryInterface;

/**
 * @method Channel find(mixed $id)
 */
interface ChannelRepositoryInterface extends RepositoryInterface
{
    /**
     * 根据应用ID查找渠道
     */
    public function findByAppId(string $appId): Collection;

    /**
     * 根据渠道类型查找渠道
     */
    public function findByChannelType(ChannelType $channelType): Collection;

    /**
     * 根据应用ID和渠道类型查找渠道
     */
    public function findByAppIdAndChannelType(string $appId, ChannelType $channelType): ?Channel;

    /**
     * 查找启用的渠道
     */
    public function findEnabled(): Collection;

    /**
     * 查找启用的渠道（按应用ID）
     */
    public function findEnabledByAppId(string $appId): Collection;

    /**
     * 检查应用是否已有该类型的渠道
     */
    public function channelTypeExistsForApp(string $appId, ChannelType $channelType, ?string $excludeId = null): bool;
}
