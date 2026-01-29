<?php

namespace HuiZhiDa\Core\Domain\Conversation\Repositories;

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

    public function pendingInputMessages(string $conversationId, array $messages) : void;

    /**
     * @param  string  $conversationId
     * @param  int|null  $beforeTimestamp  只获取此时间戳之前的消息（不包含此时间戳）
     *
     * @return ChannelMessage[]
     */
    public function getPendingMessages(string $conversationId, ?int $beforeTimestamp = null) : array;

    /**
     * @param  string  $conversationId
     * @param  int|null  $beforeTimestamp  只删除此时间戳之前的消息（不包含此时间戳）
     *
     * @return void
     */
    public function removePendingInputMessages(string $conversationId, ?int $beforeTimestamp = null) : void;
}
