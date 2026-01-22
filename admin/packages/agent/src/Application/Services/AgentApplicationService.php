<?php

namespace HuiZhiDa\Agent\Application\Services;

use HuiZhiDa\Agent\Domain\Models\Agent;
use HuiZhiDa\Agent\Domain\Repositories\AgentRepositoryInterface;
use HuiZhiDa\Agent\Domain\Transformers\AgentTransformer;
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
