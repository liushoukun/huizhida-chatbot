<?php

namespace HuiZhiDa\Engine\Core\Domain\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum ActionType: string
{
    use EnumsHelper;

    // 忽略
    case  Ignore = 'ignore';
    // 继续
    case  Continue = 'continue';
    //
    case  TransferHuman = 'TransferHuman';


}
