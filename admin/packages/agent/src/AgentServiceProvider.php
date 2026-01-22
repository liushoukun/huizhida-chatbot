<?php

namespace HuiZhiDa\Agent;

use Illuminate\Support\ServiceProvider;
use HuiZhiDa\Agent\Domain\Repositories\AgentRepositoryInterface;
use HuiZhiDa\Agent\Infrastructure\Repositories\AgentRepository;

class AgentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/agent.php', 'agent');

        // 注册仓库实现
        $this->app->bind(AgentRepositoryInterface::class, AgentRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/agent.php' => config_path('agent.php'),
        ], 'agent-config');

        // 发布数据库迁移
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'agent-migrations');

        // 发布语言文件
        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/agent'),
        ], 'agent-lang');

        // 加载数据库迁移
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // 加载语言文件
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'agent');
    }
}
