<?php

namespace HuiZhiDa\Core\Application\Services;

use HuiZhiDa\Core\Domain\Conversation\Contracts\MessageRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\Models\Conversation;
use RedJasmine\Support\Application\ApplicationService;
use RedJasmine\Support\Domain\Queries\FindQuery;

/**
 * Conversation application service.
 * @method Conversation find(FindQuery $query)
 */
class ConversationApplicationService extends ApplicationService
{

    public function __construct(
        protected MessageRepositoryInterface $messageRepository
    ) {
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
            'channelId'             => $model->channel_id,
            'appId'                 => $model->app_id,
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

    public function updateAgentConversationId(string $conversationId, string $agentConversationId) : null
    {
        return Conversation::where('conversation_id', $conversationId)->update(['agent_conversation_id' => $agentConversationId]);
    }

    /**
     * Save pending message.
     *
     * @param  ChannelMessage  $message
     *
     * @return null
     */
    public function savePendingMessage(ChannelMessage $message) : null
    {
        return $this->messageRepository->pending($message);
    }

    /**
     * Get pending messages.
     *
     * @param  string  $conversationId
     *
     * @return ChannelMessage[]
     */
    public function getPendingMessages(string $conversationId) : array
    {
        return $this->messageRepository->getPendingMessages($conversationId);
    }


    public function removePendingMessages(string $conversationId) : null
    {
        return $this->messageRepository->removePendingMessages($conversationId);
    }

}