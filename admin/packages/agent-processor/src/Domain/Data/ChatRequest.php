<?php

namespace HuiZhiDa\AgentProcessor\Domain\Data;

use RedJasmine\Support\Foundation\Data\Data;

/**
 * 聊天请求数据对象
 */
class ChatRequest extends Data
{
    /**
     * 会话ID
     * @var string
     */
    public string $conversationId;

    /**
     * 代理会话ID
     * @var string|null
     */
    public ?string $agentConversationId = null;

    /**
     * 消息列表
     * @var Message[]
     */
    public array $messages = [];

    /**
     * 历史记录
     * @var array
     */
    public array $history = [];

    /**
     * 上下文信息
     * @var array
     */
    public array $context = [];

    /**
     * 用户信息
     * @var array
     */
    public array $userInfo = [];

    /**
     * 时间戳
     * @var int|null
     */
    public ?int $timestamp = null;
}
