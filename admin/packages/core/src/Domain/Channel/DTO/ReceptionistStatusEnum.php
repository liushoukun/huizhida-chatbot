<?php

namespace HuiZhiDa\Core\Domain\Channel\DTO;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum ReceptionistStatusEnum: string
{
    use EnumsHelper;

    case Online = 'online';
    case Offline = 'offline';

    public static function labels() : array
    {
        return [
            self::Online->value  => 'Online',
            self::Offline->value => 'Offline',
        ];

    }

}
