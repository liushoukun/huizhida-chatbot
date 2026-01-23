<?php

namespace HuiZhiDa\Core\Domain\Conversation\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum ConversationQueueType: string
{
    use EnumsHelper;

    case Processor = 'processor'; // 处理器队列

    case Sending = 'sending'; // 发送者

    /**
     * 获取队列名称
     * @return string
     */
    public function getQueueName() : string
    {
        return "conversations:queue-{$this->value}";
    }
}