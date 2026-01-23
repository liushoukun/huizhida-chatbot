<?php

namespace HuiZhiDa\Core\Domain\Message\DTO;

use HuiZhiDa\Core\Domain\Message\Enums\UserType;
use RedJasmine\Support\Foundation\Data\Data;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;

/**
 * 用户信息DTO
 */
class UserInfo extends Data
{

    /**
     * 用户类型
     * @var UserType
     */
    #[WithCast(EnumCast::class, UserType::class)]
    public UserType $type = UserType::User;

    /**
     * 用户ID
     * @var string
     */
    public string $id;

    /**
     * 昵称
     * @var string|null
     */
    public ?string $nickname = null;

    /**
     * 头像
     * @var string|null
     */
    public ?string $avatar = null;

}
