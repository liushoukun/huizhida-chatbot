<?php

namespace HuiZhiDa\Message\Domain\DTO;

use HuiZhiDa\Message\Domain\DTO\Contents\Content;
use HuiZhiDa\Message\Domain\Enums\ContentType;
use HuiZhiDa\Message\Domain\Enums\MessageType;
use RedJasmine\Support\Foundation\Data\Data;

/**
 * 核心消息DTO
 *
 * 这是消息的核心数据结构，用于在不同包之间传递消息数据
 */
class Message extends Data
{
    /**
     * 用户信息
     * @var UserInfo|null
     */
    public ?UserInfo $user = null;

    /**
     * 内部会话ID
     * @var string|null
     */
    public ?string $conversationId = null;

    /**
     * 内部对话ID
     * @var string|null
     */
    public ?string $chatId = null;

    /**
     * 内部消息ID
     * @var string|null
     */
    public ?string $messageId = null;

    /**
     * 消息类型
     * @var MessageType
     */
    public MessageType $type = MessageType::Question;

    /**
     * 消息内容类型
     * @var ContentType
     */
    public ContentType $contentType = ContentType::Text;

    /**
     * 消息内容对象
     * @var Content|null
     */
    public ?Content $content = null;

    /**
     * 时间戳
     * @var int
     */
    public int $timestamp;

    /**
     * 原始数据
     * @var string|null
     */
    public ?string $rawData = null;

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->timestamp = time();
    }
}
