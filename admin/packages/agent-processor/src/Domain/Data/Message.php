<?php

namespace HuiZhiDa\AgentProcessor\Domain\Data;

use RedJasmine\Support\Foundation\Data\Data;

/**
 * 消息数据对象
 */
class Message extends Data
{
    /**
     * 消息内容
     * 可以是字符串或包含 text 键的数组
     * @var string|array
     */
    public string|array $content;

    /**
     * 消息类型（可选）
     * @var string|null
     */
    public ?string $type = null;

    /**
     * 消息ID（可选）
     * @var string|null
     */
    public ?string $id = null;

    /**
     * 时间戳（可选）
     * @var int|null
     */
    public ?int $timestamp = null;

    /**
     * 获取文本内容
     * @return string
     */
    public function getText(): string
    {
        if (is_array($this->content) && isset($this->content['text'])) {
            return $this->content['text'];
        }
        if (is_string($this->content)) {
            return $this->content;
        }
        return '';
    }
}
