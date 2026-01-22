<?php

namespace HuiZhiDa\Message\Domain\DTO;

class AgentMessage extends Message
{
    /**
     *  内部智能体ID
     * @var ?int
     */
    public ?int $agentId;

    // 智能体会话Id
    public string $agentlConversationId;
    // 智能体 chat id
    public string $agentlChatId;
    // 智能体消息id
    public string $agentlMessageId;

}