<?php

namespace HuiZhiDa\Message\Domain\DTO;

use RedJasmine\Support\Foundation\Data\Data;

/**
 * 消息内容DTO
 */
class MessageContent extends Data
{
    /**
     * 文本内容
     * @var string
     */
    public string $text = '';

    /**
     * 媒体URL
     * @var string
     */
    public string $mediaUrl = '';

    /**
     * 媒体类型
     * @var string
     */
    public string $mediaType = '';

    /**
     * 扩展信息
     * @var array
     */
    public array $extra = [];
}
