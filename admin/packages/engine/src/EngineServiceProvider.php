<?php

namespace HuiZhiDa\Engine;

use HuiZhiDa\Core\Domain\Agent\Repositories\AgentRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Engine\Channel\Application\Services\GatewayApplicationService;
use HuiZhiDa\Engine\Channel\UI\Http\Controllers\CallbackController;
use HuiZhiDa\Engine\Channel\Infrastructure\Adapters\AdapterFactory as ChannelAdapterFactory;
use HuiZhiDa\Engine\Channel\UI\Consoles\Commands\CallbackQueueCommand;
use HuiZhiDa\Engine\Channel\UI\Consoles\Commands\ConversationOutputQueueCommand;
use HuiZhiDa\Engine\Core\Application\Services\EngineCoreService;
use HuiZhiDa\Engine\Core\Domain\Services\PreCheckService;
use HuiZhiDa\Engine\Core\Infrastructure\Queue\RedisQueue;
use HuiZhiDa\Engine\Core\UI\Consoles\Commands\ConversationInputQueueCommand;
use HuiZhiDa\Engine\Agent\Application\Services\AgentService;
use HuiZhiDa\Engine\Agent\Infrastructure\Adapters\AgentAdapterFactory;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EngineServiceProvider extends ServiceProvider
{
    public function register() : void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/engine.php', 'engine');

        // Core: 队列唯一实现
        $this->app->singleton(ConversationQueueInterface::class, function ($app) {
            $config = config('engine.queue', []);
            return new RedisQueue($config);
        });

        // Core: 预检服务
        $this->app->singleton(PreCheckService::class);

        // Core: 消息处理编排
        $this->app->singleton(EngineCoreService::class, function ($app) {
            return new EngineCoreService(
                $app->make(ConversationApplicationService::class),
                $app->make(ConversationQueueInterface::class),
                $app->make(PreCheckService::class),
                $app->make(AgentService::class)
            );
        });

        // Channel: 渠道适配器与应用服务
        $this->app->singleton(ChannelAdapterFactory::class);
        $this->app->singleton(GatewayApplicationService::class);

        // Agent: 智能体适配器与服务
        $this->app->singleton(AgentAdapterFactory::class);
        $this->app->singleton(AgentService::class, function ($app) {
            return new AgentService(
                $app->make(AgentRepositoryInterface::class),
                $app->make(AgentAdapterFactory::class)
            );
        });
    }

    public function boot() : void
    {
        $this->publishes([
            __DIR__.'/../config/engine.php' => config_path('engine.php'),
        ], 'engine-config');

        $this->loadRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CallbackQueueCommand::class,
                ConversationOutputQueueCommand::class,
                ConversationInputQueueCommand::class,
            ]);
        }
    }

    protected function loadRoutes() : void
    {
        Route::middleware('api')
             ->prefix('api/gateway')
             ->group(function () {
                 Route::get('{channel}/{appId}', [CallbackController::class, 'health']);
                 Route::post('{channel}/{appId}', [CallbackController::class, 'handle']);
             });
    }
}
