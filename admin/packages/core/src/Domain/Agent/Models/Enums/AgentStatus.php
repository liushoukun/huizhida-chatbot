<?php

namespace HuiZhiDa\Core\Domain\Agent\Models\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum AgentStatus: int
{
    use EnumsHelper;

    case DISABLED = 0;
    case ENABLED = 1;

    public static function labels(): array
    {
        return [
            self::DISABLED->value => '禁用',
            self::ENABLED->value => '启用',
        ];
    }

    public static function colors(): array
    {
        return [
            self::DISABLED->value => 'gray',
            self::ENABLED->value => 'green',
        ];
    }

    public static function icons(): array
    {
        return [
            self::DISABLED->value => 'heroicon-o-x-circle',
            self::ENABLED->value => 'heroicon-o-check-circle',
        ];
    }
}
