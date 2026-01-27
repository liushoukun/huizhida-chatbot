<?php

namespace HuiZhiDa\Core\Domain\Conversation\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum MessageType: string
{

    use EnumsHelper;

    // 事件
    case Event = 'event';
    // 对话
    case Chat = 'chat';


    public static function labels() : array
    {
        return [
            self::Chat->value  => '消息',
            self::Event->value => '事件',

        ];
    }


}
