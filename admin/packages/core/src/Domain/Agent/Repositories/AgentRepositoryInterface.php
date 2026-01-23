<?php

namespace HuiZhiDa\Core\Domain\Agent\Repositories;

use Illuminate\Database\Eloquent\Collection;
use HuiZhiDa\Core\Domain\Agent\Models\Agent;
use RedJasmine\Support\Domain\Contracts\UserInterface;
use RedJasmine\Support\Domain\Repositories\RepositoryInterface;

interface AgentRepositoryInterface extends RepositoryInterface
{
    /**
     * 根据所有者查找智能体
     */
    public function findByOwner(UserInterface $owner): Collection;

    /**
     * 根据类型查找智能体
     */
    public function findByType(string $type): Collection;

    /**
     * 根据提供者查找智能体
     */
    public function findByProvider(string $provider): Collection;

    /**
     * 查找启用的智能体
     */
    public function findEnabled(): Collection;

    /**
     * 查找启用的智能体（按所有者）
     */
    public function findEnabledByOwner(UserInterface $owner): Collection;

    /**
     * 检查智能体名称是否已存在（按所有者）
     */
    public function nameExists(UserInterface $owner, string $name, ?string $excludeId = null): bool;
}
