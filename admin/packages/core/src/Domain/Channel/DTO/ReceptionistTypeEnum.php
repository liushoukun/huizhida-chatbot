<?php

namespace HuiZhiDa\Core\Domain\Channel\DTO;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum ReceptionistTypeEnum: string
{

    use EnumsHelper;


    case Member = 'member';

    case  Department = 'department';


    public static function labels() : array
    {
        return [
            self::Member->value     => 'Member',
            self::Department->value => 'Department',
        ];
    }

}
