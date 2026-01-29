<?php

namespace HuiZhiDa\Core\Application\Services;

use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationStatus;
use HuiZhiDa\Core\Domain\Conversation\Models\Conversation;
use HuiZhiDa\Core\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\Repositories\MessageRepositoryInterface;
use RedJasmine\Support\Application\ApplicationService;
use RedJasmine\Support\Domain\Queries\FindQuery;

/**
 * Conversation application service.
 * @method Conversation find(FindQuery $query)
 */
class ConversationApplicationService extends ApplicationService
{

    public function __construct(
        public MessageRepositoryInterface $messageRepository,
        public ConversationRepositoryInterface $conversationRepository,
        public ConversationQueueInterface $mq,

    ) {
    }

    // 触发事件 等待处理
    public function triggerEvent(ConversationEvent $conversationEvent) : void
    {
        // 记录最后一次事件
        $this->mq->recordLastEvent($conversationEvent);

        $this->mq->publish($conversationEvent->queue, $conversationEvent);
    }

    /**
     * 转换状态
     *
     * @param  string  $conversationId
     * @param  ConversationStatus  $status
     * @param  string|null  $servicer
     *
     * @return void
     */
    public function transfer(string $conversationId, ConversationStatus $status,?string  $servicer = null) : void
    {
        $conversation = $this->conversationRepository->findByConversationId($conversationId);
        $conversation->transferHuman($servicer);
        $this->conversationRepository->update($conversation);
    }

    /**
     * 获取会话
     */
    public function get(string $conversationId) : ?ConversationData
    {
        $model = Conversation::where('conversation_id', $conversationId)->firstOrFail();

        return $model ? ConversationData::from([
            'conversationId'        => $model->conversation_id,
            'agentConversationId'   => $model->agent_conversation_id,
            'channelConversationId' => $model->channel_conversation_id,
            'channelAppId'          => $model->channel_app_id,
            'channelId'             => $model->channel_id,
            'status'                => $model->status,
            'user'                  => [
                'type'     => $model->user_type,
                'id'       => $model->user_id,
                'nickname' => $model->user_nickname,
                'avatar'   => $model->user_avatar,
            ],
        ]) : null;
    }

    /**
     * @param  string  $conversationId
     *
     * @return Conversation
     */
    public function findConversation(string $conversationId) : Conversation
    {
        return Conversation::where('conversation_id', $conversationId)->firstOrFail();
    }

    public function updateAgentConversationId(string $conversationId, string $agentConversationId) : void
    {
        Conversation::where('conversation_id', $conversationId)->update(['agent_conversation_id' => $agentConversationId]);
    }


    /**
     *
     * @param  string  $conversationId
     * @param  ChannelMessage[]  $messages
     *
     * @return void
     */
    public function savePendingInputMessages(string $conversationId, array $messages) : void
    {
        // 持久化保存
        foreach ($messages as $message) {
            $this->messageRepository->save($message);
        }

        $this->messageRepository->pendingInputMessages($conversationId, $messages);

    }



    /**
     * Get pending messages.
     *
     * @param  string  $conversationId
     *
     * @return ChannelMessage[]
     */
    public function getPendingInputMessages(string $conversationId) : array
    {
        return $this->messageRepository->getPendingMessages($conversationId);
    }


    public function removePendingInputMessages(string $conversationId) : null
    {
        return $this->messageRepository->removePendingInputMessages($conversationId);
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

        $lastConversation = Conversation::where('channel_id', $message->channelId)
                                        ->where('channel_app_id', $message->channelAppId)
                                        ->where('user_type', $message->sender->type)
                                        ->where('user_id', $message->sender->id)
                                        ->orderByDesc('id')
                                        ->first();

        if (($lastConversation && $lastConversation->status !== ConversationStatus::Closed)) {
            return $lastConversation;
        }

        $conversation                          = new Conversation();
        $conversation->app_id                  = $message->appId;
        $conversation->conversation_id         = ConversationData::buildID();
        $conversation->channel_app_id          = $message->channelAppId;
        $conversation->channel_conversation_id = $message->channelConversationId;
        $conversation->channel_id              = $message->channelId;
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


}