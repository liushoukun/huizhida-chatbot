<?php

namespace HuiZhiDa\Core\Domain\Conversation\Models;

use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{


    protected function casts() : array
    {
        return [
            'status' => ConversationStatus::class
        ];
    }
}
