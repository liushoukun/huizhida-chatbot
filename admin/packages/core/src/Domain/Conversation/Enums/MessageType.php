<?php

namespace HuiZhiDa\Core\Domain\Conversation\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum MessageType: string
{

    use EnumsHelper;

    // 提问
    case Question = 'question';
    // 回答
    case Answer = 'answer';
    // 事件
    case Event = 'event';
    // 通知
    case Notification = 'notification';
    // 提示
    case Tip = 'tip';

    public static function labels() : array
    {
        return [
            self::Question->value     => '提前',
            self::Answer->value       => '提示',
            self::Event->value        => '事件',
            self::Notification->value => '通知',
            self::Tip->value          => '提示',
        ];
    }


}
