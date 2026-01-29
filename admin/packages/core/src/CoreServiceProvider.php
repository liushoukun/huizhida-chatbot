<?php

namespace HuiZhiDa\Core;

use HuiZhiDa\Core\Domain\Agent\Repositories\AgentRepositoryInterface;
use HuiZhiDa\Core\Domain\Channel\Repositories\ChannelRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\Repositories\MessageRepositoryInterface;
use HuiZhiDa\Core\Infrastructure\Repositories\AgentRepository;
use HuiZhiDa\Core\Infrastructure\Repositories\ChannelRepository;
use HuiZhiDa\Core\Infrastructure\Repositories\ConversationRepository;
use HuiZhiDa\Core\Infrastructure\Repositories\MessageRepository;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register() : void
    {

        $this->mergeConfigFrom(__DIR__.'/../config/huizhida.php', 'huizhida');

        // 注册 Agent 仓库实现
        $this->app->bind(AgentRepositoryInterface::class, AgentRepository::class);

        // 注册 Channel 仓库实现
        $this->app->bind(ChannelRepositoryInterface::class, ChannelRepository::class);

        $this->app->bind(MessageRepositoryInterface::class, MessageRepository::class);

        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);


    }

    /**
     * Bootstrap services.
     */
    public function boot() : void
    {


        $this->publishes([
            __DIR__.'/../config/huizhida.php' => config_path('huizhida.php'),
        ], 'huizhida-config');


        // 发布数据库迁移
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'huizhida-migrations');

        // 发布语言文件
        $this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/core'),
        ], 'huizhida-lang');

        // 加载数据库迁移
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // 加载语言文件
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'huizhida');
    }
}
