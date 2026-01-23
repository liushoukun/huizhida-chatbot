<?php

namespace HuiZhiDa\Core;

use Illuminate\Support\ServiceProvider;
use HuiZhiDa\Core\Domain\Agent\Repositories\AgentRepositoryInterface;
use HuiZhiDa\Core\Infrastructure\Repositories\AgentRepository;
use HuiZhiDa\Core\Domain\Channel\Repositories\ChannelRepositoryInterface;
use HuiZhiDa\Core\Infrastructure\Repositories\ChannelRepository;
use HuiZhiDa\Core\Domain\Conversation\Services\ConversationService;
use HuiZhiDa\Core\Domain\Conversation\Services\MessageService;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Agent 配置
        $this->mergeConfigFrom(__DIR__ . '/../config/agent.php', 'agent');
        
        // Channel 配置
        $this->mergeConfigFrom(__DIR__ . '/../config/channel.php', 'channel');

        // 注册 Agent 仓库实现
        $this->app->bind(AgentRepositoryInterface::class, AgentRepository::class);
        
        // 注册 Channel 仓库实现
        $this->app->bind(ChannelRepositoryInterface::class, ChannelRepository::class);
        
        // 注册 Conversation 服务
        $this->app->singleton(ConversationService::class);
        
        // 注册 Message 服务
        $this->app->singleton(MessageService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 发布 Agent 配置文件
        $this->publishes([
            __DIR__ . '/../config/agent.php' => config_path('agent.php'),
        ], 'core-agent-config');

        // 发布 Channel 配置文件
        $this->publishes([
            __DIR__ . '/../config/channel.php' => config_path('channel.php'),
        ], 'core-channel-config');

        // 发布数据库迁移
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'core-migrations');

        // 发布语言文件
        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/core'),
        ], 'core-lang');

        // 加载数据库迁移
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // 加载语言文件
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'core');
    }
}
