<?php

namespace HuiZhiDa\AgentProcessor\Domain\Contracts;

use HuiZhiDa\AgentProcessor\Domain\Data\ChatRequest;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatResponse;

/**
 * 智能体适配器接口
 */
interface AgentAdapterInterface
{
    /**
     * 初始化智能体
     *
     * @param array $config 智能体配置
     * @return void
     */
    public function initialize(array $config): void;

    /**
     * 发送消息并获取回复
     *
     * @param ChatRequest $request 聊天请求
     * @return ChatResponse 聊天响应
     */
    public function chat(ChatRequest $request): ChatResponse;

    /**
     * 健康检查
     *
     * @return bool
     */
    public function healthCheck(): bool;
}
