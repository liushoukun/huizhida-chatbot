<?php

namespace HuiZhiDa\Agent\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use HuiZhiDa\Agent\Domain\Models\Agent;
use HuiZhiDa\Agent\Domain\Models\Enums\AgentStatus;
use HuiZhiDa\Agent\Domain\Repositories\AgentRepositoryInterface;
use RedJasmine\Support\Domain\Contracts\UserInterface;
use RedJasmine\Support\Domain\Queries\Query;
use RedJasmine\Support\Infrastructure\Repositories\Repository;

class AgentRepository extends Repository implements AgentRepositoryInterface
{
    protected static string $modelClass = Agent::class;

    public function findByOwner(UserInterface $owner): Collection
    {
        return Agent::where('owner_type', $owner->getType())
            ->where('owner_id', $owner->getID())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByType(string $type): Collection
    {
        return Agent::where('agent_type', $type)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findByProvider(string $provider): Collection
    {
        return Agent::where('provider', $provider)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findEnabled(): Collection
    {
        return Agent::where('status', AgentStatus::ENABLED)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function findEnabledByOwner(UserInterface $owner): Collection
    {
        return Agent::where('owner_type', $owner->getType())
            ->where('owner_id', $owner->getID())
            ->where('status', AgentStatus::ENABLED)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function nameExists(UserInterface $owner, string $name, ?string $excludeId = null): bool
    {
        $query = Agent::where('owner_type', $owner->getType())
            ->where('owner_id', $owner->getID())
            ->where('name', $name);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    protected function allowedFilters(?Query $query = null): array
    {
        return [
            'owner_type',
            'owner_id',
            'name',
            'agent_type',
            'provider',
            'status',
        ];
    }

    protected function allowedSorts(?Query $query = null): array
    {
        return [
            'id',
            'name',
            'agent_type',
            'provider',
            'status',
            'created_at',
            'updated_at',
        ];
    }

    protected function allowedIncludes(?Query $query = null): array
    {
        return [
            'owner',
            'fallbackAgent',
            'agentsUsingAsFallback',
        ];
    }
}
