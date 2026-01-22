<?php

namespace HuiZhiDa\AgentProcessor\Domain\Data;

use RedJasmine\Support\Foundation\Data\Data;

/**
 * 聊天响应数据对象
 */
class ChatResponse extends Data
{
    /**
     * 回复内容
     * @var string
     */
    public string $reply = '';

    /**
     * 回复类型
     * @var string
     */
    public string $replyType = 'text';

    /**
     * 富文本内容
     * @var array|null
     */
    public ?array $richContent = null;

    /**
     * 是否应该转接
     * @var bool
     */
    public bool $shouldTransfer = false;

    /**
     * 转接原因
     * @var string|null
     */
    public ?string $transferReason = null;

    /**
     * 置信度
     * @var float
     */
    public float $confidence = 1.0;

    /**
     * 代理会话ID
     * @var string|null
     */
    public ?string $agentConversationId = null;

    /**
     * 元数据
     * @var array|null
     */
    public ?array $metadata = null;
}
