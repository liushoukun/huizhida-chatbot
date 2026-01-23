<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO;

use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\Content;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use RedJasmine\Support\Domain\Contracts\UserInterface;
use RedJasmine\Support\Foundation\Data\Data;

/**
 * 核心消息DTO
 *
 *  接受渠道或者智能体的消息，先有 外部ID 存储后 得到内部消息ID
 *  把处理器 把消息转发给 渠道或者 智能体，有内部消息ID,发送给外部
 */
class Message extends Data
{

    public ?UserInterface $sender = null;


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
    public MessageType $messageType = MessageType::Question;

    /**
     * 消息内容类型
     * @var ContentType
     */
    public ContentType $contentType = ContentType::Text;

    /**
     * 消息内容对象
     * @var array|null
     */
    public ?array $content = null;

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


    public function setContentData(ContentType $contentType, ?array $content = null) : static
    {
        $this->content = $content;
        // TODO 根据类型验证
        return $this;
    }

    public function getContent() : ?Content
    {
        return null;
    }
}
