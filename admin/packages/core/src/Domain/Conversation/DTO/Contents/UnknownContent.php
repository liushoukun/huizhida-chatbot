<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO\Contents;

/**
 * 未知内容DTO
 * 
 * 用于处理不支持或未知类型的内容
 */
class UnknownContent extends Content
{
    /**
     * 原始数据
     * @var array|null
     */
    public ?array $rawData = null;
}
