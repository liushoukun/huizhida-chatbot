<?php

namespace HuiZhiDa\Core\Domain\Agent\Models\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum AgentType: string
{
    use EnumsHelper;

    case LOCAL = 'local';
    case REMOTE = 'remote';
    case HYBRID = 'hybrid';

    public static function labels(): array
    {
        return [
            self::LOCAL->value => '本地智能体',
            self::REMOTE->value => '远程智能体',
            self::HYBRID->value => '组合智能体',
        ];
    }

    public static function colors(): array
    {
        return [
            self::LOCAL->value => 'blue',
            self::REMOTE->value => 'green',
            self::HYBRID->value => 'purple',
        ];
    }

    public static function icons(): array
    {
        return [
            self::LOCAL->value => 'heroicon-o-server',
            self::REMOTE->value => 'heroicon-o-cloud',
            self::HYBRID->value => 'heroicon-o-cpu-chip',
        ];
    }
}
