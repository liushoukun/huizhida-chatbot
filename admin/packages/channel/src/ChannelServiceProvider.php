<?php

namespace HuiZhiDa\Channel;

use Illuminate\Support\ServiceProvider;
use HuiZhiDa\Channel\Domain\Repositories\ChannelRepositoryInterface;
use HuiZhiDa\Channel\Infrastructure\Repositories\ChannelRepository;

class ChannelServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/channel.php', 'channel');

        // 注册仓库实现
        $this->app->bind(ChannelRepositoryInterface::class, ChannelRepository::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/channel.php' => config_path('channel.php'),
        ], 'channel-config');

        // 发布数据库迁移
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'channel-migrations');

        // 发布语言文件
        $this->publishes([
            __DIR__ . '/../resources/lang' => resource_path('lang/vendor/channel'),
        ], 'channel-lang');

        // 加载数据库迁移
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // 加载语言文件
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'channel');
    }
}
