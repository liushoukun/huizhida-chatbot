<?php

namespace HuiZhiDa\Message\Domain\Enums;

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


}