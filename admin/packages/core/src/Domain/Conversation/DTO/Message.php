<?php

namespace HuiZhiDa\Core\Domain\Conversation\DTO;

use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\Content;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\EventContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\FileContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\ImageContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\MarkdownContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\TextContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\UnknownContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\VideoContent;
use HuiZhiDa\Core\Domain\Conversation\DTO\Contents\VoiceContent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use RedJasmine\Support\Domain\Contracts\UserInterface;
use RedJasmine\Support\Foundation\Data\Data;
use RedJasmine\Support\Helpers\ID\DatetimeIdGenerator;

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
    public MessageType $messageType = MessageType::Chat;

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
     * 扩展信息
     * @var array
     */
    public array $exrra = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        $this->timestamp = time();
    }


    public function setContentData(ContentType $contentType, ?array $content = null) : static
    {
        // TODO 格式验证


        $this->content = $content;

        return $this;
    }

    public function getContent() : ?Content
    {
        // 如果内容为空，返回 null
        if ($this->content === null) {
            return null;
        }

        // 根据内容类型创建对应的内容对象
        return match ($this->contentType) {
            ContentType::Text => TextContent::from($this->content),
            ContentType::Image => ImageContent::from($this->content),
            ContentType::Voice => VoiceContent::from($this->content),
            ContentType::Video => VideoContent::from($this->content),
            ContentType::File => FileContent::from($this->content),
            ContentType::Event => EventContent::from($this->content),
            ContentType::Markdown => MarkdownContent::from($this->content),
            ContentType::Unknown => UnknownContent::from($this->content),
            default => null, // Card, Event, Combination 等类型暂不支持
        };
    }


    public function getMessageId() : string
    {
        if (!$this->messageId) {
            $this->messageId = static::buildID();
        }
        return $this->messageId;
    }


    public static function buildID() : string
    {
        return DatetimeIdGenerator::buildId();
    }
}
