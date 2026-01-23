<?php

namespace HuiZhiDa\Core\Application\Services;

use HuiZhiDa\Core\Domain\Conversation\Contracts\MessageRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use RedJasmine\Support\Application\ApplicationService;

class ConversationApplicationService extends ApplicationService
{

    public function __construct(
        protected MessageRepositoryInterface $messageRepository
    ) {
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