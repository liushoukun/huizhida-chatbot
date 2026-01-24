<?php

namespace HuiZhiDa\Core\Domain\Conversation\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum MessageType: string
{

    use EnumsHelper;

    // 事件
    case Event = 'event';
    // 提问
    case Message = 'message';
    // 回答
    case Answer = 'answer';

    // 通知
    case Notification = 'notification';
    // 提示
    case Tip = 'tip';

    public static function labels() : array
    {
        return [
            self::Message->value      => '消息',
            self::Event->value        => '事件',
            self::Notification->value => '通知',
            self::Tip->value          => '提示',
        ];
    }


}
