<?php

namespace HuiZhiDa\Message\Domain\Services;

class CommonService
{


    /**
     * 生成 待处理消息存储名称
     *
     *
     * @param  string  $event
     *
     * @return string
     */
    public function getEventKey(string $event) : string
    {
        return "conversations:event-{$event}";
    }


    /**
     * 生成 待发送消息存储名称
     *
     * @param  string  $conversationId
     *
     * @return string
     */
    public function generateSendingMessagesKey(string $conversationId) : string
    {
        return "conversations:sending-messages:$conversationId";
    }

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