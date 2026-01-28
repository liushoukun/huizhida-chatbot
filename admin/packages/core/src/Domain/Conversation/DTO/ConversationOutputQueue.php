<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO;

/**
 *  会话回答数据
 *
 */
class ConversationOutputQueue extends ConversationData
{

    /**
     *
     * @var ChannelMessage[]
     */
    public array $messages;
}