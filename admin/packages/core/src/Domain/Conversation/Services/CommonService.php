<?php

namespace HuiZhiDa\Core\Domain\Conversation\Services;

use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;

class CommonService
{


    /**
     * 生成 待处理消息存储名称
     *
     * @param  string  $conversationId
     *
     * @return string
     */
    public function generatePendingMessagesKey(string $conversationId) : string
    {
        return "conversations:pending-messages:$conversationId";
    }

}
