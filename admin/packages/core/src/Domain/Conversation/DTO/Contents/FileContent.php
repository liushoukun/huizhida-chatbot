<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO\Contents;

/**
 * 文件内容DTO
 */
class FileContent extends Content
{
    /**
     * 文件URL
     * @var string
     */
    public string $url = '';

    /**
     * 文件名
     * @var string
     */
    public string $filename = '';

    /**
     * 文件大小（字节）
     * @var int|null
     */
    public ?int $size = null;

    /**
     * 文件扩展名
     * @var string|null
     */
    public ?string $extension = null;
}
