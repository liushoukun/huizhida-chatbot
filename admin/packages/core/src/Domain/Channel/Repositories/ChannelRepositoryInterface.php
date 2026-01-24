<?php

namespace HuiZhiDa\Core\Domain\Channel\Repositories;

use HuiZhiDa\Core\Domain\Channel\Models\Channel;
use Illuminate\Database\Eloquent\Collection;
use RedJasmine\Support\Domain\Repositories\RepositoryInterface;

/**
 * @method Channel find(mixed $id)
 */
interface ChannelRepositoryInterface extends RepositoryInterface
{
    /**
     * 根据应用ID查找渠道
     */
    public function findByAppId(string $appId) : Collection;


    /**
     * 查找启用的渠道
     */
    public function findEnabled() : Collection;

    /**
     * 查找启用的渠道（按应用ID）
     */
    public function findEnabledByAppId(string $appId) : Collection;


}
