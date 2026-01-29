<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO\Contents;

/**
 * 媒体内容基类
 *
 * 所有媒体类型内容（图片、视频、语音、文件）都应该继承此类
 */
class MediaContent extends Content
{
    /**
     * 存储磁盘名称
     * @var string|null
     */
    public ?string $disk = null;

    /**
     * 文件存储路径（相对于storage）
     * @var string|null
     */
    public ?string $path = null;

    /**
     * 媒体类型（image, voice, video, file）
     * @var string|null
     */
    public ?string $type = null;


    public ?string $url;
}
