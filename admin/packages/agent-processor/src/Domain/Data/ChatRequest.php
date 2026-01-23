<?php

namespace HuiZhiDa\AgentProcessor\Domain\Data;

use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use RedJasmine\Support\Domain\Contracts\UserInterface;
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
     * @var ChannelMessage[]
     */
    public array $messages = [];

    /**
     * 用户信息
     * @var UserInterface
     */
    public UserInterface $user;

    /**
     * 时间戳
     * @var int|null
     */
    public ?int $timestamp = null;
}
