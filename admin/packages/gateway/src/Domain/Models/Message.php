<?php

namespace HuiZhiDa\Gateway\Domain\Models;

class Message
{
    // 消息方向类型
    public const DIRECTION_INCOMING = 'incoming';  // 接收的消息
    public const DIRECTION_OUTGOING = 'outgoing';  // 发送的消息
    public const DIRECTION_TRANSFER = 'transfer';   // 转接请求

    // 消息内容类型
    public const TYPE_TEXT = 'text';
    public const TYPE_IMAGE = 'image';
    public const TYPE_VOICE = 'voice';
    public const TYPE_VIDEO = 'video';
    public const TYPE_FILE = 'file';
    public const TYPE_LINK = 'link';
    public const TYPE_LOCATION = 'location';
    public const TYPE_EVENT = 'event';
    public const TYPE_RICH = 'rich';  // 富文本消息

    // 基础字段（所有消息类型共有）
    public string $messageId;
    public string $direction;  // incoming, outgoing, transfer
    public string $appId;
    public string $channel;  // 渠道类型: wecom, taobao等
    public ?int $channelId = null;  // 渠道ID（关联channels表）
    public ?string $channelConversationId = null;  // 渠道会话ID（渠道方提供）
    public ?string $conversationId = null;  // 系统会话ID
    public int $timestamp;
    public ?string $rawData = null;

    // 接收消息字段 (direction = incoming)
    public ?string $channelMessageId = null;
    public ?UserInfo $user = null;
    public ?string $messageType = null;  // text, image, voice, video, file, link, location, event
    public ?MessageContent $content = null;

    // 发送消息字段 (direction = outgoing)
    public ?string $reply = null;
    public ?string $replyType = null;  // text, rich
    public ?array $richContent = null;

    // 转接请求字段 (direction = transfer)
    public ?string $reason = null;
    public ?string $source = null;  // rule, agent
    public ?string $agentReason = null;
    public ?string $priority = null;  // high, normal, low
    public ?string $mode = null;  // queue, specific
    public ?string $specificServicer = null;
    public ?array $context = null;

    public function __construct(string $direction = self::DIRECTION_INCOMING)
    {
        $this->direction = $direction;
        $this->timestamp = time();

        if ($direction === self::DIRECTION_INCOMING) {
            $this->user = new UserInfo();
            $this->content = new MessageContent();
        }
    }

    /**
     * 创建接收消息
     */
    public static function createIncoming(): self
    {
        return new self(self::DIRECTION_INCOMING);
    }

    /**
     * 创建发送消息
     */
    public static function createOutgoing(): self
    {
        return new self(self::DIRECTION_OUTGOING);
    }

    /**
     * 创建转接请求
     */
    public static function createTransfer(): self
    {
        return new self(self::DIRECTION_TRANSFER);
    }

    /**
     * 判断是否为接收消息
     */
    public function isIncoming(): bool
    {
        return $this->direction === self::DIRECTION_INCOMING;
    }

    /**
     * 判断是否为发送消息
     */
    public function isOutgoing(): bool
    {
        return $this->direction === self::DIRECTION_OUTGOING;
    }

    /**
     * 判断是否为转接请求
     */
    public function isTransfer(): bool
    {
        return $this->direction === self::DIRECTION_TRANSFER;
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $data = [
            'message_id' => $this->messageId,
            'direction' => $this->direction,
            'app_id' => $this->appId,
            'channel' => $this->channel,
            'channel_id' => $this->channelId,
            'channel_conversation_id' => $this->channelConversationId,
            'conversation_id' => $this->conversationId,
            'timestamp' => $this->timestamp,
        ];

        if ($this->rawData !== null) {
            $data['raw_data'] = $this->rawData;
        }

        // 接收消息字段
        if ($this->isIncoming()) {
            $data['channel_message_id'] = $this->channelMessageId;
            $data['user'] = $this->user?->toArray();
            $data['message_type'] = $this->messageType;
            $data['content'] = $this->content?->toArray();
        }

        // 发送消息字段
        if ($this->isOutgoing()) {
            $data['reply'] = $this->reply;
            $data['reply_type'] = $this->replyType;
            if (!empty($this->richContent)) {
                $data['rich_content'] = $this->richContent;
            }
        }

        // 转接请求字段
        if ($this->isTransfer()) {
            $data['reason'] = $this->reason;
            $data['source'] = $this->source;
            $data['priority'] = $this->priority;
            if ($this->agentReason !== null) {
                $data['agent_reason'] = $this->agentReason;
            }
            if ($this->mode !== null) {
                $data['mode'] = $this->mode;
            }
            if ($this->specificServicer !== null) {
                $data['specific_servicer'] = $this->specificServicer;
            }
            if (!empty($this->context)) {
                $data['context'] = $this->context;
            }
        }

        return $data;
    }

    /**
     * 从数组创建消息对象
     */
    public static function fromArray(array $data): self
    {
        $direction = $data['direction'] ?? self::DIRECTION_INCOMING;
        $message = new self($direction);

        // 基础字段
        $message->messageId = $data['message_id'] ?? '';
        $message->appId = $data['app_id'] ?? '';
        $message->channel = $data['channel'] ?? '';
        $message->channelId = isset($data['channel_id']) ? (int)$data['channel_id'] : null;
        $message->channelConversationId = $data['channel_conversation_id'] ?? null;
        $message->conversationId = $data['conversation_id'] ?? null;
        $message->timestamp = $data['timestamp'] ?? time();
        $message->rawData = $data['raw_data'] ?? null;

        // 接收消息字段
        if ($message->isIncoming()) {
            $message->channelMessageId = $data['channel_message_id'] ?? null;
            $message->messageType = $data['message_type'] ?? null;
            
            if (isset($data['user'])) {
                $message->user = new UserInfo();
                $userData = is_array($data['user']) ? $data['user'] : [];
                $message->user->channelUserId = $userData['channel_user_id'] ?? '';
                $message->user->nickname = $userData['nickname'] ?? '';
                $message->user->avatar = $userData['avatar'] ?? '';
                $message->user->isVip = $userData['is_vip'] ?? false;
                $message->user->tags = $userData['tags'] ?? [];
            }

            if (isset($data['content'])) {
                $message->content = new MessageContent();
                $contentData = is_array($data['content']) ? $data['content'] : [];
                $message->content->text = $contentData['text'] ?? '';
                $message->content->mediaUrl = $contentData['media_url'] ?? '';
                $message->content->mediaType = $contentData['media_type'] ?? '';
                $message->content->extra = $contentData['extra'] ?? [];
            }
        }

        // 发送消息字段
        if ($message->isOutgoing()) {
            $message->reply = $data['reply'] ?? null;
            $message->replyType = $data['reply_type'] ?? 'text';
            $message->richContent = $data['rich_content'] ?? null;
            // 兼容旧字段名
            if (isset($data['session_id']) && !isset($data['conversation_id'])) {
                $message->conversationId = $data['session_id'];
            }
        }

        // 转接请求字段
        if ($message->isTransfer()) {
            $message->reason = $data['reason'] ?? null;
            $message->source = $data['source'] ?? null;
            $message->agentReason = $data['agent_reason'] ?? null;
            $message->priority = $data['priority'] ?? 'normal';
            $message->mode = $data['mode'] ?? null;
            $message->specificServicer = $data['specific_servicer'] ?? null;
            $message->context = $data['context'] ?? null;
            // 兼容旧字段名
            if (isset($data['session_id']) && !isset($data['conversation_id'])) {
                $message->conversationId = $data['session_id'];
            }
        }

        return $message;
    }
}
