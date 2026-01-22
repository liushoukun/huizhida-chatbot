<?php

namespace HuiZhiDa\Gateway\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Gateway\Domain\Models\Message;

class MessageService
{
    public function __construct()
    {
    }

    /**
     * 保存消息
     */
    public function save(Message $message): void
    {
        if ($message->isIncoming()) {
            $this->saveIncoming($message);
        } elseif ($message->isOutgoing()) {
            $this->saveOutgoing($message);
        }
        // transfer 类型的消息不需要保存到 messages 表
    }

    /**
     * 保存接收的消息
     */
    protected function saveIncoming(Message $message): void
    {
        if (!$message->content) {
            throw new \InvalidArgumentException('Incoming message must have content');
        }

        $contentData = json_encode($message->content->toArray());

        try {
            DB::table('messages')->insert([
                'message_id' => $message->messageId,
                'conversation_id' => $message->conversationId,
                'app_id' => $message->appId,
                'channel' => $message->channel,
                'direction' => 1, // 接收
                'message_type' => $message->messageType ?? 'text',
                'content' => $contentData,
                'channel_message_id' => $message->channelMessageId,
                'sender_type' => 'user',
                'status' => 'pending',
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Save incoming message failed', [
                'error' => $e->getMessage(),
                'message_id' => $message->messageId,
            ]);
            throw $e;
        }
    }

    /**
     * 保存发送的消息
     */
    protected function saveOutgoing(Message $message): void
    {
        $contentData = json_encode([
            'text' => $message->reply ?? '',
        ]);

        try {
            DB::table('messages')->insert([
                'message_id' => $message->messageId,
                'conversation_id' => $message->conversationId,
                'app_id' => $message->appId,
                'channel' => $message->channel,
                'direction' => 2, // 发送
                'message_type' => $message->replyType ?? 'text',
                'content' => $contentData,
                'sender_type' => 'agent',
                'status' => 'sent',
                'created_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Save outgoing message failed', [
                'error' => $e->getMessage(),
                'message_id' => $message->messageId,
            ]);
            throw $e;
        }
    }

    /**
     * 更新消息状态
     */
    public function updateStatus(string $messageId, string $status): void
    {
        DB::table('messages')
            ->where('message_id', $messageId)
            ->update(['status' => $status]);
    }
}
