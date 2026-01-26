<?php

namespace HuiZhiDa\Core\Domain\Conversation\Services;

use Exception;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Message;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationStatus;
use HuiZhiDa\Core\Domain\Conversation\Models\Conversation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ConversationService extends CommonService
{
    public function __construct(
        protected ConversationQueueInterface $mq,

    ) {
    }

    // 触发事件 等待处理
    public function triggerEvent(ConversationEvent $conversationEvent) : void
    {
        try {
            $this->mq->recordLastEvent($conversationEvent);
            $this->mq->publish($conversationEvent->queue, $conversationEvent);
        } catch (Exception $e) {
            Log::error('Publish conversation event failed', $conversationEvent->toArray());
            // 继续处理，不返回错误
            throw $e;
        }

    }

    /**
     * 获取未处理的消息
     *
     * @param  string  $conversationId
     *
     * @return Message[]
     */
    public function getUnprocessedMessages(string $conversationId) : array
    {
        return [];
    }

    /**
     * 获取或创建会话
     */
    public function getOrCreate(ChannelMessage $message) : Conversation
    {
        // 内置会话服务
        // 根据、应用、渠道、用户，获取最后一次会话
        if (!$message->sender) {
            throw new InvalidArgumentException('Message must have sender info');
        }

        $lastConversation = Conversation::where('app_id', $message->appId)
                                        ->where('channel_id', $message->channelId)
                                        ->where('channel_app_id', $message->channelAppId)
                                        ->where('user_type', $message->sender->type)
                                        ->where('user_id', $message->sender->id)
                                        ->orderByDesc('id')
                                        ->first();

        if (($lastConversation && $lastConversation->status !== ConversationStatus::Closed)) {
            $lastConversation->updated_at = Carbon::now();
            $lastConversation->save();
            return $lastConversation;
        }

        $conversation                          = new Conversation();
        $conversation->conversation_id         = ConversationData::buildID();
        $conversation->channel_app_id          = $message->channelAppId;
        $conversation->channel_conversation_id = $message->channelConversationId;
        $conversation->channel_id              = $message->channelId;
        $conversation->app_id                  = $message->appId;
        $conversation->agent_id                = null;
        $conversation->user_type               = $message->sender->type;
        $conversation->user_id                 = $message->sender->id;
        $conversation->user_nickname           = $message->sender->nickname;
        $conversation->user_avatar             = $message->sender->avatar;
        $conversation->status                  = ConversationStatus::Pending;
        $conversation->context                 = json_encode([
            'history'   => [],
            'variables' => [],
        ]);
        $conversation->save();
        return $conversation;

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

        if (in_array($status, [ConversationStatus::HumanQueuing->value, ConversationStatus::Human->value])) {
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


}
