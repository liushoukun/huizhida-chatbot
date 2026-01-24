<?php

namespace HuiZhiDa\Gateway\Infrastructure\Adapters;

use HuiZhiDa\Gateway\Domain\Contracts\ChannelAdapterInterface;
use InvalidArgumentException;

class AdapterFactory
{
    protected array $adapters = [];

    public function __construct()
    {
        // 注册默认适配器
        $this->register('work-wechat', WorkWechatAdapter::class);
        $this->register('api', ApiAdapter::class);
    }

    /**
     * 注册适配器
     */
    public function register(string $channel, string $adapterClass): void
    {
        if (!is_subclass_of($adapterClass, ChannelAdapterInterface::class)) {
            throw new InvalidArgumentException("Adapter class must implement ChannelAdapterInterface");
        }

        $this->adapters[$channel] = $adapterClass;
    }

    /**
     * 获取适配器实例
     */
    public function get(string $channel, array $config = []): ChannelAdapterInterface
    {
        if (!isset($this->adapters[$channel])) {
            throw new InvalidArgumentException("Unsupported channel: {$channel}");
        }

        $adapterClass = $this->adapters[$channel];
        return new $adapterClass($config);
    }

    /**
     * 获取支持的渠道列表
     */
    public function getSupportedChannels(): array
    {
        return array_keys($this->adapters);
    }
}
