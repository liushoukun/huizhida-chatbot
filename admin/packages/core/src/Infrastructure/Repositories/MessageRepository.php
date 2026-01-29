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
     * @param  string  $conversationId
     * @param  int|null  $beforeTimestamp  只获取此时间戳之前的消息（不包含此时间戳）
     * @return ChannelMessage[]
     */
    public function getPendingMessages(string $conversationId, ?int $beforeTimestamp = null) : array
    {
        $key      = $this->generatePendingInputMessagesKey($conversationId);
        $redis    = Redis::connection($this->getRedisConnection());

        // 如果指定了时间戳，使用 zrangebyscore 获取该时间之前的消息
        if ($beforeTimestamp !== null) {
            // 使用 beforeTimestamp - 1 确保不包含该时间戳，-inf 表示从负无穷开始
            $messages = $redis->zrangebyscore($key, '-inf', $beforeTimestamp - 1);
        } else {
            // 如果没有指定时间戳，获取所有消息
            $messages = $redis->zrange($key, 0, -1);
        }

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
            $dictionary[$message->toJson()] = $message->timestamp;
        }
        $redisConnection = $this->getRedisConnection();
        Redis::connection($redisConnection)->zadd($key, $dictionary);
    }

    /**
     * 移除待处理消息
     *
     * @param  string  $conversationId
     * @param  int|null  $beforeTimestamp  只删除此时间戳之前的消息（不包含此时间戳）
     *
     * @return void
     */
    public function removePendingInputMessages(string $conversationId, ?int $beforeTimestamp = null) : void
    {
        $key   = $this->generatePendingInputMessagesKey($conversationId);
        $redis = Redis::connection($this->getRedisConnection());

        // 如果指定了时间戳，使用 zremrangebyscore 删除该时间之前的消息
        if ($beforeTimestamp !== null) {
            // 使用 beforeTimestamp - 1 确保不包含该时间戳，-inf 表示从负无穷开始
            $redis->zremrangebyscore($key, '-inf', $beforeTimestamp - 1);
        } else {
            // 如果没有指定时间戳，删除所有消息
            $redis->del($key);
        }
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
        return "conversations:messages:pending-input:$conversationId";
    }


}
