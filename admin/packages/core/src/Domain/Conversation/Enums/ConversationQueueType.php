<?php

namespace HuiZhiDa\Core\Domain\Conversation\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum ConversationQueueType: string
{
    use EnumsHelper;


    case Inputs = 'inputs'; // 输入队列
    case Outputs = 'outputs'; // 输出队列
    case Callback = 'callback'; // 回调处理队列


    /**
     * 获取队列名称
     * @return string
     */
    public function getQueueName() : string
    {
        return "conversations:queue:{$this->value}";
    }

    /**
     * 获取延时队列的延时秒数
     * 返回 null 表示不使用延时队列，立即处理
     *
     * @return int|null
     */
    public function getDelaySeconds() : ?int
    {
        return match ($this) {
            self::Inputs => config('huizhida.inputs_delay', 30), // 输入队列延时30秒，用于防抖处理
            self::Outputs, self::Callback => null, // 输出队列立即处理
            // 回调队列立即处理
        };
    }
}
