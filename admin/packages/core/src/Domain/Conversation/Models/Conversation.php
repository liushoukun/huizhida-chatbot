<?php

namespace HuiZhiDa\Core\Domain\Conversation\Models;

use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property ConversationStatus $status
 */
class Conversation extends Model
{


    protected function casts() : array
    {
        return [
            'status'        => ConversationStatus::class,
            'transfer_time' => 'datetime'
        ];
    }

    public function updateStatus(ConversationStatus $status) : void
    {

        $this->status = $status;

    }

    public function transferHuman(?string $servicer = null) : void
    {
        $this->updateStatus(ConversationStatus::Human);
        if ($servicer) {
            $this->servicer = $servicer;
        }

        if (!$this->transfer_time) {
            $this->transfer_time = Carbon::now();
        }
    }
}
