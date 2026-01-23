<?php

namespace HuiZhiDa\Core\Domain\Message\DTO\Contents;

/**
 * 图片内容DTO
 */
class ImageContent extends Content
{
    /**
     * 图片URL
     * @var string
     */
    public string $url = '';

    /**
     * 图片宽度（像素）
     * @var int|null
     */
    public ?int $width = null;

    /**
     * 图片高度（像素）
     * @var int|null
     */
    public ?int $height = null;

    /**
     * 图片格式（jpg, png, gif等）
     * @var string|null
     */
    public ?string $format = null;
}
