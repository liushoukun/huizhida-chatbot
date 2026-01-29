<?php

namespace HuiZhiDa\Engine\Core\Domain\Data;

use HuiZhiDa\Engine\Core\Domain\Enums\ActionType;
use RedJasmine\Support\Foundation\Data\Data;

class PreCheckResult extends Data
{

    public ActionType $actionType = ActionType::Ignore;

}
