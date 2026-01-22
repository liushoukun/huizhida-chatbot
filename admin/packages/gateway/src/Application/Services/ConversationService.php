<?php

namespace HuiZhiDa\Gateway\Application\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Gateway\Domain\Models\Message;

class ConversationService
{
    public function __construct()
    {
    }

    /**
     * 获取或创建会话
     */
    public function getOrCreate(Message $message): array
    {
        if (!$message->isIncoming() || !$message->user) {
            throw new \InvalidArgumentException('Message must be incoming type with user info');
        }

        $conversationId = $this->generateConversationId(
            $message->channel,
            $message->user->channelUserId,
            $message->appId
        );

        $conversation = DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->first();

        if (!$conversation) {
            // 创建新会话
            $contextData = json_encode([
                'history' => [],
                'variables' => [],
            ]);

            // 从消息中获取 channel_id，如果没有则尝试查询
            $channelId = $message->channelId;
            if (!$channelId) {
                // 尝试根据 channel_type 和 app_id 查询 channels 表获取
                $channel = DB::table('channels')
                    ->where('channel', $message->channel)
                    ->where('app_id', $message->appId)
                    ->first();
                $channelId = $channel ? $channel->id : null;
            }

            DB::table('conversations')->insert([
                'conversation_id' => $conversationId,
                'channel_conversation_id' => $message->channelConversationId, // 渠道会话ID，如果渠道提供则设置
                'channel_type' => $message->channel,
                'channel_id' => $channelId,
                'app_id' => $message->appId,
                'channel_user_id' => $message->user->channelUserId,
                'user_nickname' => $message->user->nickname,
                'user_avatar' => $message->user->avatar,
                'is_vip' => $message->user->isVip ? 1 : 0,
                'user_tags' => !empty($message->user->tags) ? json_encode($message->user->tags) : null,
                'user_extra' => null, // 扩展信息
                'status' => 'active',
                'assigned_agent_id' => null,
                'current_agent_id' => null,
                'context' => $contextData,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $conversation = DB::table('conversations')->where('conversation_id', $conversationId)->first();
            
            Log::info('Created new conversation', ['conversation_id' => $conversationId]);
        } else {
            // 更新会话时间和用户信息（如果用户信息有变化）
            $updates = ['updated_at' => now()];
            
            // 更新用户信息（如果消息中包含更新的信息）
            if ($message->user->nickname && ($conversation->user_nickname !== $message->user->nickname)) {
                $updates['user_nickname'] = $message->user->nickname;
            }
            if ($message->user->avatar && ($conversation->user_avatar !== $message->user->avatar)) {
                $updates['user_avatar'] = $message->user->avatar;
            }
            if (!empty($message->user->tags)) {
                $updates['user_tags'] = json_encode($message->user->tags);
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
    public function get(string $conversationId): ?array
    {
        $conversation = DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->first();

        return $conversation ? (array) $conversation : null;
    }

    /**
     * 更新会话
     */
    public function update(string $conversationId, array $updates): void
    {
        $updates['updated_at'] = now();
        
        DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->update($updates);
    }

    /**
     * 更新会话状态
     */
    public function updateStatus(string $conversationId, string $status, ?string $reason = null, ?string $source = null): void
    {
        $updates = [
            'status' => $status,
            'updated_at' => now(),
        ];

        if ($reason) {
            $updates['transfer_reason'] = $reason;
        }

        if ($source) {
            $updates['transfer_source'] = $source;
        }

        if (in_array($status, ['pending_human', 'transferred'])) {
            $updates['transfer_time'] = now();
        }

        DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->update($updates);
    }

    /**
     * 分配 Agent
     */
    public function assignAgent(string $conversationId, int $agentId, bool $isCurrent = false): void
    {
        $updates = [
            'assigned_agent_id' => $agentId,
            'updated_at' => now(),
        ];

        if ($isCurrent) {
            $updates['current_agent_id'] = $agentId;
        }

        DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->update($updates);
    }

    /**
     * 更新当前处理的 Agent
     */
    public function updateCurrentAgent(string $conversationId, ?int $agentId): void
    {
        DB::table('conversations')
            ->where('conversation_id', $conversationId)
            ->update([
                'current_agent_id' => $agentId,
                'updated_at' => now(),
            ]);
    }

    /**
     * 生成会话ID
     */
    protected function generateConversationId(string $channel, string $channelUserId, string $appId): string
    {
        return "{$channel}_{$channelUserId}_{$appId}";
    }
}
