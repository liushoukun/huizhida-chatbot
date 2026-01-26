<?php

namespace HuiZhiDa\Core\Domain\Conversation\Repositories;

use HuiZhiDa\Core\Domain\Conversation\Models\Conversation;
use RedJasmine\Support\Domain\Repositories\RepositoryInterface;

interface ConversationRepositoryInterface extends RepositoryInterface
{

    public function findByConversationId(string $ConversationId) : Conversation;

}