<?php

namespace HuiZhiDa\Message\Domain\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum UserType: string
{
    use EnumsHelper;

    case User = 'user';

    case Assistant = 'assistant';

    // 员工
    case Employee = 'employee';


}
