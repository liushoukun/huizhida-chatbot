<?php

namespace HuiZhiDa\Gateway;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use HuiZhiDa\Message\Domain\Contracts\MessageQueueInterface;
use HuiZhiDa\Gateway\Infrastructure\Adapters\AdapterFactory;
use HuiZhiDa\Gateway\Infrastructure\Queue\RedisQueue;
use HuiZhiDa\Gateway\Domain\Services\ConversationService;
use HuiZhiDa\Gateway\Domain\Services\MessageService;

class GatewayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/gateway.php', 'gateway');

        // 注册适配器工厂
        $this->app->singleton(AdapterFactory::class, function ($app) {
            return new AdapterFactory();
        });

        // 注册队列实现
        $this->app->bind(MessageQueueInterface::class, function ($app) {
            $config = config('gateway.queue');
            return new RedisQueue($config);
        });

        // 注册服务
        $this->app->singleton(ConversationService::class);
        $this->app->singleton(MessageService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 发布配置文件
        $this->publishes([
            __DIR__ . '/../config/gateway.php' => config_path('gateway.php'),
        ], 'gateway-config');




        // 加载路由
        $this->loadRoutes();

        // 注册命令
        if ($this->app->runningInConsole()) {
            $this->commands([
                \HuiZhiDa\Gateway\Console\Commands\MessageSenderCommand::class,
                \HuiZhiDa\Gateway\Console\Commands\TransferExecutorCommand::class,
            ]);
        }
    }

    /**
     * 加载路由
     */
    protected function loadRoutes(): void
    {
        Route::middleware('api')
            ->prefix('api/gateway')
            ->group(function () {
                Route::get('/health', function () {
                    return response()->json(['status' => 'ok']);
                });

                Route::post('/callback/{channel}/{appId}', [
                    \HuiZhiDa\Gateway\Http\Controllers\CallbackController::class,
                    'handle'
                ]);
            });
    }
}
