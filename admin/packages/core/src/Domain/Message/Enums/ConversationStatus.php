<?php

namespace HuiZhiDa\Core\Domain\Message\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

/**
 * 会话状态
 *
 */
enum ConversationStatus: string
{
    use EnumsHelper;

    // 未处理
    // 智能处理中
    // 人工接待排队中
    // 人工处理中
    // 已结束


    case Pending = 'pending';
    case Agent = 'agent'; // 智能体接待中
    case HumanQueuing = 'humanQueuing'; // 人工排队中
    case Human = 'human'; // 人工接待中
    case Closed = 'closed'; // 已结束


    public static function labels() : array
    {
        return [
            self::Pending->value      => '待处理',
            self::Agent->value        => '智能体处理中',
            self::HumanQueuing->value => '人工排队中',
            self::Human->value        => '人工接待',
            self::Closed->value       => '已结束',

        ];
    }

}
