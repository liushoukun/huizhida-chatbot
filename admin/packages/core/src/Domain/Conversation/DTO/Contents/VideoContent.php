<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO\Contents;

/**
 * 视频内容DTO
 */
class VideoContent extends MediaContent
{


    /**
     * 视频缩略图URL
     * @var string
     */
    public string $thumbUrl = '';

    /**
     * 视频时长（秒）
     * @var int|null
     */
    public ?int $duration = null;

    /**
     * 视频宽度（像素）
     * @var int|null
     */
    public ?int $width = null;

    /**
     * 视频高度（像素）
     * @var int|null
     */
    public ?int $height = null;

    /**
     * 视频格式（mp4, avi等）
     * @var string|null
     */
    public ?string $format = null;
}
