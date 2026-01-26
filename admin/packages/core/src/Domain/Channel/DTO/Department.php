<?php

namespace HuiZhiDa\Core\Domain\Channel\DTO;

use RedJasmine\Support\Foundation\Data\Data;

class Department extends Data
{
    public string $id;

    public ?string $name = null;

    /**
     * 子部门列表（树形结构时使用）
     *
     * @var array<int, array{id: string, name: ?string, children?: array}>
     */
    public array $children = [];
}
