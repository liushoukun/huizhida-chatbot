<?php

namespace HuiZhiDa\Core\Domain\Conversation\Services;

use Exception;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Message;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use ReflectionException;

class MessageService extends CommonService
{




    /**
     * 保存消息
     */
    public function save(ChannelMessage $message) : void
    {
        if (!$message->conversationId) {
            throw new InvalidArgumentException('Message must have conversation_id');
        }

        if (!$message->content) {
            throw new InvalidArgumentException('Message must have content');
        }

        $contentData = $message->content;

        try {
            // 生成消息ID（如果还没有）
            if (!$message->messageId) {
                $message->messageId = $this->generateMessageId();
            }

            // 获取渠道ID
            $channelId = $message->channelId ? (int) $message->channelId : null;

            DB::table('messages')->insert([
                'id'                 => $this->generateId(),
                'message_id'         => $message->messageId,
                'conversation_id'    => $message->conversationId,
                'chat_id'            => $message->chatId,
                'status'             => 'pending',
                'app_id'             => $message->appId ?? null,
                'message_type'       => $message->messageType->value,
                'content_type'       => $message->contentType->value,
                'content'            => json_encode($contentData),
                'raw_data'           => $message->rawData,
                'sender_type'        => $message->sender?->type->value ?? 'user',
                'sender_id'          => $message->sender?->id ?? '',
                'sender_nickname'    => $message->sender?->nickname,
                'sender_avatar'      => $message->sender?->avatar,
                'channel_id'         => $channelId,
                'channel_message_id' => $message->channelMessageId,
                'channel_chat_id'    => $message->channelChatId,
                'agent_id'           => null,
                'agent_message_id'   => null,
                'agent_chat_id'      => null,
                'timestamp'          => $message->timestamp,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        } catch (Exception $e) {
            Log::error('Save message failed', [
                'error'      => $e->getMessage(),
                'message_id' => $message->messageId,
            ]);
            throw $e;
        }
    }

    /**
     * 更新消息状态
     */
    public function updateStatus(string $messageId, string $status) : void
    {
        DB::table('messages')
          ->where('message_id', $messageId)
          ->update(['status' => $status]);
    }

    /**
     * 生成消息ID
     */
    protected function generateMessageId() : string
    {
        return 'msg_'.uniqid('', true);
    }

    /**
     * 生成数据库ID（雪花ID或自增ID）
     */
    protected function generateId() : int
    {
        // 这里可以使用雪花ID生成器，暂时使用时间戳+随机数
        return (int) (time() * 1000 + mt_rand(100, 999));
    }
}
