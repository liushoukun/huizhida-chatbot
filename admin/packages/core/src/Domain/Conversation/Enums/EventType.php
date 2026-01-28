<?php

namespace HuiZhiDa\Core\Domain\Conversation\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

/**
 * 事件类型
 */
enum EventType: string
{
    use EnumsHelper;


    // 转入人工处理处理池子
    case TransferToHumanQueue = 'transferToHumanQueue';
    // 关闭会话
    case Closed = 'closed';
}
