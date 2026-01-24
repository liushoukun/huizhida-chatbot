<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO\Contents;

/**
 * 语音内容DTO
 */
class VoiceContent extends Content
{
    /**
     * 语音文件URL
     * @var string
     */
    public string $url = '';

    /**
     * 语音时长（秒）
     * @var int|null
     */
    public ?int $duration = null;

    /**
     * 语音格式（amr, mp3等）
     * @var string|null
     */
    public ?string $format = null;
}
