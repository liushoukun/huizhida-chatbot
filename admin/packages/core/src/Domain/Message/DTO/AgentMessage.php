<?php

namespace HuiZhiDa\Core\Domain\Message\DTO;

/**
 * 智能体消息DTO
 * 
 * 继承自核心Message，添加智能体相关的特定字段
 */
class AgentMessage extends Message
{
    /**
     * 内部智能体ID
     * @var int|null
     */
    public ?int $agentId = null;

    /**
     * 智能体会话ID
     * @var string|null
     */
    public ?string $agentConversationId = null;

    /**
     * 智能体聊天ID
     * @var string|null
     */
    public ?string $agentChatId = null;

    /**
     * 智能体消息ID
     * @var string|null
     */
    public ?string $agentMessageId = null;
}
