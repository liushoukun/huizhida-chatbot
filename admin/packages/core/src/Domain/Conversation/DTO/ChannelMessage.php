<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO;

/**
 * 渠道消息DTO
 *
 * 继承自核心Message，添加渠道相关的特定字段
 */
class ChannelMessage extends Message
{
    /**
     * 应用ID
     * @var int|null
     */
    public ?int $appId = null;

    /**
     * 渠道ID
     * @var string|null
     */
    public ?string $channelId = null;

    /**
     * 渠道会话ID
     * @var string|null
     */
    public ?string $channelConversationId = null;

    /**
     * 渠道聊天ID
     * @var string|null
     */
    public ?string $channelChatId = null;

    /**
     * 渠道消息ID
     * @var string|null
     */
    public ?string $channelMessageId = null;
}
