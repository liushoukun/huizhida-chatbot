<?php

namespace HuiZhiDa\AgentProcessor;

use HuiZhiDa\AgentProcessor\Application\Services\AgentService;
use HuiZhiDa\AgentProcessor\Application\Services\PreCheckService;
use HuiZhiDa\AgentProcessor\Infrastructure\Adapters\AgentAdapterFactory;
use HuiZhiDa\Core\Domain\Agent\Repositories\AgentRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Gateway\Infrastructure\Queue\RedisQueue;
use Illuminate\Support\ServiceProvider;
use HuiZhiDa\AgentProcessor\Console\Commands\ProcessConversationEventsCommand;

class AgentProcessorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register() : void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/agent-processor.php',
            'agent-processor'
        );

        // 注册服务
        $this->app->singleton(PreCheckService::class);
        $this->app->singleton(AgentAdapterFactory::class);

        $this->app->singleton(AgentService::class, function ($app) {
            return new AgentService(
                $app->make(AgentRepositoryInterface::class),
                $app->make(AgentAdapterFactory::class)
            );
        });

        // $this->app->singleton(MessageProcessorService::class, function ($app) {
        //     return new MessageProcessorService(
        //         $app->make(ConversationQueueInterface::class),
        //         $app->make(ConversationService::class),
        //         $app->make(PreCheckService::class),
        //         $app->make(AgentService::class)
        //     );
        // });

        // 注册消息队列接口实现
        $this->app->singleton(ConversationQueueInterface::class, function ($app) {
            $config = config('agent-processor.queue', []);
            return new RedisQueue($config);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot() : void
    {
        $this->publishes([
            __DIR__.'/../config/agent-processor.php' => config_path('agent-processor.php'),
        ], 'agent-processor-config');

        // 发布数据库迁移
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'agent-processor-migrations');

        // 加载数据库迁移
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessConversationEventsCommand::class,
            ]);
        }
    }
}
