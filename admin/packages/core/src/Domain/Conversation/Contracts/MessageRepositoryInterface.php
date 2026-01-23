<?php

namespace HuiZhiDa\Core\Domain\Conversation\Contracts;

use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Message;

/**
 * 消息存储接口
 */
interface MessageRepositoryInterface
{


    /**
     * 存储消息
     *
     * @param  Message  $message
     *
     * @return void
     */
    public function save(Message $message) : void;

    /**
     * 推送待处理消息
     *
     * @param  ChannelMessage  $message
     *
     * @return void
     */
    public function pending(ChannelMessage $message) : void;

    /**
     * @param  string  $conversationId
     *
     * @return ChannelMessage[]
     */
    public function getPendingMessages(string $conversationId) : array;

    public function removePendingMessages(string $conversationId) : void;
}