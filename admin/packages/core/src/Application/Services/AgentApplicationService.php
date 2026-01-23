<?php

namespace HuiZhiDa\Core\Application\Services;

use HuiZhiDa\Core\Domain\Agent\Models\Agent;
use HuiZhiDa\Core\Domain\Agent\Repositories\AgentRepositoryInterface;
use HuiZhiDa\Core\Domain\Agent\Transformers\AgentTransformer;
use RedJasmine\Support\Application\ApplicationService;

class AgentApplicationService extends ApplicationService
{
    public static string $hookNamePrefix = 'agent.application';
    protected static string $modelClass = Agent::class;

    public function __construct(
        public AgentRepositoryInterface $repository,
        public AgentTransformer $transformer
    ) {
    }
}
