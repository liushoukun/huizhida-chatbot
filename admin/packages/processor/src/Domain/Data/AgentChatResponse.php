<?php

namespace HuiZhiDa\Processor\Domain\Data;

use HuiZhiDa\Core\Domain\Conversation\DTO\AgentMessage;
use RedJasmine\Support\Foundation\Data\Data;

/**
 * 聊天响应数据对象
 */
class AgentChatResponse extends Data
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
     * @var AgentMessage[]
     */
    public array $messages = [];

    /**
     * 是否应该转接
     * @var bool
     */
    public bool $shouldTransfer = false;

    /**
     * 转接原因
     * @var string|null
     */
    public ?string $transferReason = null;

    /**
     * 置信度
     * @var float
     */
    public float $confidence = 1.0;


    /**
     * 元数据
     * @var array|null
     */
    public ?array $metadata = null;
}
