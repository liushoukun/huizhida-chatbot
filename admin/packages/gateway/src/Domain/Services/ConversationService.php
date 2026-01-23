<?php

namespace HuiZhiDa\Gateway\Domain\Services;

use HuiZhiDa\Message\Domain\Contracts\MessageQueueInterface;
use HuiZhiDa\Message\Domain\DTO\ConversationEvent;
use HuiZhiDa\Message\Domain\Services\CommonService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Message\Domain\DTO\ChannelMessage;
use HuiZhiDa\Message\Domain\Enums\ConversationStatus;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ConversationService
{
    public function __construct(
        protected CommonService $commonService,
        protected MessageQueueInterface $mq,

    ) {
    }

    // 触发事件 等待处理
    public function triggerEvent(ConversationEvent $conversationEvent) : void
    {

        $eventQueueName = $this->commonService->getEventKey($conversationEvent->event);
        try {
            $this->mq->publish($eventQueueName, $conversationEvent->toJson());
        } catch (Exception $e) {
            Log::error('Publish conversation event failed', $conversationEvent->toArray());
            // 继续处理，不返回错误
            throw $e;
        }

    }


    /**
     * 获取或创建会话
     */
    public function getOrCreate(ChannelMessage $message) : array
    {
        if (!$message->sender) {
            throw new InvalidArgumentException('Message must have sender info');
        }

        $conversationId = $this->generateConversationId(
            $message->channelId,
            $message->sender->id,
            $message->appId ?? null
        );

        // 查询是否有老会话ID
        $conversation = DB::table('conversations')
                          ->where('app_id', $message->appId)
                          ->where('channel_id', $message->channelId)
                          ->where('channel_id', $message->channelId)
                          ->where('channel_conversation_id', $message->channelConversationId)
                          ->orderByDesc('id')
                          ->first();

        if (!$conversation) {
            // 创建新会话
            $contextData = json_encode([
                'history'   => [],
                'variables' => [],
            ]);

            // 从消息中获取 channel_id
            $channelId = $message->channelId ? (int) $message->channelId : null;
            if (!$channelId) {
                // 尝试根据 channel 和 app_id 查询 channels 表获取
                $channel   = DB::table('channels')
                               ->where('app_id', $message->appId ?? null)
                               ->first();
                $channelId = $channel ? $channel->id : null;
            }

            DB::table('conversations')->insert([
                'conversation_id'         => $conversationId,
                'channel_conversation_id' => $message->channelConversationId,
                'channel_id'              => $channelId,
                'app_id'                  => $message->appId ?? null,
                'agent_id'                => null,
                'user_type'               => $message->sender->type->value ?? 'user',
                'user_id'                 => $message->sender->id,
                'user_nickname'           => $message->sender->nickname,
                'user_avatar'             => $message->sender->avatar,
                'status'                  => ConversationStatus::Pending->value,
                'context'                 => $contextData,
                'created_at'              => now(),
                'updated_at'              => now(),
            ]);

            $conversation = DB::table('conversations')->where('conversation_id', $conversationId)->first();

            Log::info('Created new conversation', ['conversation_id' => $conversationId]);
        } else {
            // 更新会话时间和用户信息（如果用户信息有变化）
            $updates = ['updated_at' => now()];

            // 更新用户信息（如果消息中包含更新的信息）
            if ($message->sender->nickname && ($conversation->user_nickname !== $message->sender->nickname)) {
                $updates['user_nickname'] = $message->sender->nickname;
            }
            if ($message->sender->avatar && ($conversation->user_avatar !== $message->sender->avatar)) {
                $updates['user_avatar'] = $message->sender->avatar;
            }

            DB::table('conversations')
              ->where('conversation_id', $conversationId)
              ->update($updates);
        }

        return (array) $conversation;
    }

    /**
     * 获取会话
     */
    public function get(string $conversationId) : ?array
    {
        $conversation = DB::table('conversations')
                          ->where('conversation_id', $conversationId)
                          ->first();

        return $conversation ? (array) $conversation : null;
    }

    /**
     * 更新会话
     */
    public function update(string $conversationId, array $updates) : void
    {
        $updates['updated_at'] = now();

        DB::table('conversations')
          ->where('conversation_id', $conversationId)
          ->update($updates);
    }

    /**
     * 更新会话状态
     */
    public function updateStatus(string $conversationId, string $status, ?string $reason = null, ?string $source = null) : void
    {
        $updates = [
            'status'     => $status,
            'updated_at' => now(),
        ];

        if ($reason) {
            $updates['transfer_reason'] = $reason;
        }

        if ($source) {
            $updates['transfer_source'] = $source;
        }

        if (in_array($status, [ConversationStatus::Human_queuing->value, ConversationStatus::Human->value])) {
            $updates['transfer_time'] = now();
        }

        DB::table('conversations')
          ->where('conversation_id', $conversationId)
          ->update($updates);
    }

    /**
     * 分配 Agent
     */
    public function assignAgent(string $conversationId, int $agentId) : void
    {
        DB::table('conversations')
          ->where('conversation_id', $conversationId)
          ->update([
              'agent_id'   => $agentId,
              'updated_at' => now(),
          ]);
    }

    /**
     * 分配人工客服
     */
    public function assignHuman(string $conversationId, string $humanId) : void
    {
        DB::table('conversations')
          ->where('conversation_id', $conversationId)
          ->update([
              'assigned_human' => $humanId,
              'updated_at'     => now(),
          ]);
    }

    /**
     * 生成会话ID
     */
    protected function generateConversationId(?string $channelId, string $userId, ?int $appId) : string
    {
        return Str::uuid();
        $parts = [];
        // if ($channelId) {
        //     $parts[] = $channelId;
        // }
        // $parts[] = $userId;
        // if ($appId) {
        //     $parts[] = $appId;
        // }
        return implode('_', $parts);
    }
}
