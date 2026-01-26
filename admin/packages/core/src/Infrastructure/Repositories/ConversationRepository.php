<?php

namespace HuiZhiDa\Core\Infrastructure\Repositories;

use HuiZhiDa\Core\Domain\Conversation\Models\Conversation;
use HuiZhiDa\Core\Domain\Conversation\Repositories\ConversationRepositoryInterface;
use RedJasmine\Support\Infrastructure\Repositories\Repository;

class ConversationRepository extends Repository implements ConversationRepositoryInterface
{

    protected static string $modelClass = Conversation::class;

    /**
     *
     * @param  string  $conversationId
     *
     * @return Conversation
     */
    public function findByConversationId(string $conversationId) : Conversation
    {
        return static::$modelClass::where('conversation_id', $conversationId)->firstOrFail();
    }


}