<?php

namespace HuiZhiDa\Message\Domain\DTO\Contents;

use RedJasmine\Support\Foundation\Data\Data;

/**
 * 消息内容基类
 * 
 * 所有具体的内容类型都应该继承此类
 */
abstract class Content extends Data
{
    /**
     * 内容文本
     * @var string
     */
    public string $content = '';
}