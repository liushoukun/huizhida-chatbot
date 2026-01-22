<?php

namespace HuiZhiDa\Message\Domain\DTO;

use RedJasmine\Support\Foundation\Data\Data;

/**
 * 用户信息DTO
 */
class UserInfo extends Data
{

    /**
     * 用户类型
     * @var string
     */
    public string $type;

    /**
     * 用户ID
     * @var string
     */
    public string $id;

    /**
     * 昵称
     * @var ?string
     */
    public ?string $nickname = '';

    /**
     * 头像
     * @var ?string
     */
    public ?string $avatar = null;

}
