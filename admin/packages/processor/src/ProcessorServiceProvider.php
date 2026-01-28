<?php

namespace HuiZhiDa\Processor;

use HuiZhiDa\Processor\Application\Services\AgentService;
use HuiZhiDa\Processor\Domain\Services\PreCheckService;
use HuiZhiDa\Processor\Infrastructure\Adapters\AgentAdapterFactory;
use HuiZhiDa\Processor\UI\Consoles\Commands\ConversationInputQueueCommand;
use HuiZhiDa\Core\Domain\Agent\Repositories\AgentRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Gateway\Infrastructure\Queue\RedisQueue;
use Illuminate\Support\ServiceProvider;

class ProcessorServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register() : void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/processor.php',
            'processor'
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
            $config = config('processor.queue', []);
            return new RedisQueue($config);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot() : void
    {
        $this->publishes([
            __DIR__.'/../config/processor.php' => config_path('processor.php'),
        ], 'processor-config');

        // 发布数据库迁移
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'processor-migrations');

        // 加载数据库迁移
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ConversationInputQueueCommand::class,
            ]);
        }
    }
}
