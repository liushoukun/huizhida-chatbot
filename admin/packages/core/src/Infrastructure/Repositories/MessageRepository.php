<?php

namespace HuiZhiDa\Core\Infrastructure\Repositories;

use HuiZhiDa\Core\Domain\Conversation\Contracts\MessageRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Message;
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
        $key      = $this->generatePendingMessagesKey($conversationId);
        $messages = Redis::connection($this->getRedisConnection())
                         ->zrange($key, 0, -1);

        return ChannelMessage::collect($messages);
    }

    protected function getRedisConnection() : string
    {
        return config('agent-processor.redis.connection', 'default');
    }

    public function pending(ChannelMessage $message) : void
    {
        $key         = $this->generatePendingMessagesKey($message->conversationId);
        $messageData = $message->toJson();
        $score       = microtime(true);

        $redisConnection = config('gateway.redis.connection', 'default');
        Redis::connection($redisConnection)->zadd($key, $score, $messageData);
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