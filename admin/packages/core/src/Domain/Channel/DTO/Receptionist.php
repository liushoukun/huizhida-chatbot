<?php

namespace HuiZhiDa\Core\Domain\Channel\DTO;

use RedJasmine\Support\Foundation\Data\Data;

class Receptionist extends Data
{

    public ReceptionistTypeEnum $type = ReceptionistTypeEnum::Member;

    public string $id;

    public ReceptionistStatusEnum $status = ReceptionistStatusEnum::Online;

}