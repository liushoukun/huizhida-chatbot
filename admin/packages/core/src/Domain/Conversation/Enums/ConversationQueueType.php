<?php

namespace HuiZhiDa\Core\Domain\Conversation\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum ConversationQueueType: string
{
    use EnumsHelper;


    case Inputs = 'inputs'; // 输入队列
    case Outputs = 'outputs'; // 输出队列
    // 转人工
    case Transfer = 'transfer'; // 转换会话状态

    /**
     * 获取队列名称
     * @return string
     */
    public function getQueueName() : string
    {
        return "conversations:queue:{$this->value}";
    }
}
