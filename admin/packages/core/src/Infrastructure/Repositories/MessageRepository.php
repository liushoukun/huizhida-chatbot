<?php

namespace HuiZhiDa\Core\Infrastructure\Repositories;

use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Message;
use HuiZhiDa\Core\Domain\Conversation\Repositories\MessageRepositoryInterface;
use Illuminate\Support\Facades\Redis;

class MessageRepository implements MessageRepositoryInterface
{
    public function save(Message $message) : void
    {
        // TODO:  存储到数据库
    }

    /**
     * 获取待处理消息
     * @return ChannelMessage[]
     */
    public function getPendingMessages(string $conversationId) : array
    {
        // TODO 获取一定时间内的数据
        $key             = $this->generatePendingInputMessagesKey($conversationId);
        $messages        = Redis::connection($this->getRedisConnection())
                                ->zrange($key, 0, -1);
        $channelMessages = [];
        foreach ($messages as $messageJson) {
            $channelMessages[] = ChannelMessage::from(json_decode($messageJson, true));
        }
        return $channelMessages;
    }

    protected function getRedisConnection() : string
    {
        return config('processor.redis.connection', 'default');
    }

    public function pending(ChannelMessage $message) : void
    {
        $key         = $this->generatePendingInputMessagesKey($message->conversationId);
        $messageData = $message->toJson();
        $score       = microtime(true);

        $redisConnection = $this->getRedisConnection();
        Redis::connection($redisConnection)->zadd($key, $score, $messageData);
    }

    /**
     *
     * @param  string  $conversationId
     * @param  ChannelMessage[]  $messages
     *
     * @return void
     */
    public function pendingInputMessages(string $conversationId, array $messages) : void
    {
        $key        = $this->generatePendingInputMessagesKey($conversationId);
        $dictionary = [];
        foreach ($messages as $message) {
            $dictionary[$message->toJson()] = microtime(true);
        }
        $redisConnection = $this->getRedisConnection();
        Redis::connection($redisConnection)->zadd($key, $dictionary);
    }

    /**
     * 移除待处理消息
     *
     * @param  string  $conversationId
     *
     * @return void
     */
    public function removePendingInputMessages(string $conversationId) : void
    {
        $key = $this->generatePendingInputMessagesKey($conversationId);
        Redis::connection($this->getRedisConnection())->del($key);
    }

    /**
     * 生成 待处理消息存储名称
     *
     * @param  string  $conversationId
     *
     * @return string
     */
    public function generatePendingInputMessagesKey(string $conversationId) : string
    {
        return "conversations:pending-input-messages:$conversationId";
    }


}