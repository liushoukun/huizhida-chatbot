<?php

namespace HuiZhiDa\Message;

use Illuminate\Support\ServiceProvider;

class MessageServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // 消息包主要提供DTO类，无需注册服务
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 消息包主要提供DTO类，无需启动服务
    }
}
