<?php

namespace HuiZhiDa\Processor\Domain\Data;

use HuiZhiDa\Processor\Domain\Enums\ActionType;
use RedJasmine\Support\Foundation\Data\Data;

class PreCheckResult extends Data
{

    public ActionType $actionType = ActionType::Ignore;

}