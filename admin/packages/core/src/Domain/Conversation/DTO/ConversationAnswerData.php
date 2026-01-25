<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO;

/**
 *  会话回答数据
 *
 */
class ConversationAnswerData extends ConversationData
{


    /**
     *
     * @var ChannelMessage[]
     */
    public array $messags;
}