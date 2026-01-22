<?php

namespace HuiZhiDa\Message\Domain\DTO;

class ChannelMessage extends Message
{
    /**
     * 渠道ID
     * @var string
     */
    public string $channelId;

    // 渠道会话Id
    public string $channelConversationId;
    // 渠道 chat id
    public string $channelChatId;
    // 渠道消息id
    public string $channelMessageId;


}