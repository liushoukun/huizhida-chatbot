<?php

namespace HuiZhiDa\Message\Domain\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum UserType: string
{
    use EnumsHelper;

    case User = 'user';

    case Agent = 'agent';

    // 员工
    case Human = 'human';


    public static function labels() : array
    {
        return [
            self::User->value  => '用户',
            self::Agent->value => '智能体',
            self::Human->value => '人工',
        ];
    }


}
