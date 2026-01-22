<?php

namespace HuiZhiDa\AgentProcessor\Domain\Data;

/**
 * 聊天响应数据对象
 */
readonly class ChatResponse
{
    public function __construct(
        public string $reply = '',
        public string $replyType = 'text',
        public ?array $richContent = null,
        public bool $shouldTransfer = false,
        public ?string $transferReason = null,
        public float $confidence = 1.0,
        public ?string $agentConversationId = null,
        public ?array $metadata = null,
    ) {
    }

    /**
     * 从数组创建实例
     */
    public static function fromArray(array $data): self
    {
        return new self(
            reply: $data['reply'] ?? '',
            replyType: $data['reply_type'] ?? 'text',
            richContent: $data['rich_content'] ?? null,
            shouldTransfer: (bool) ($data['should_transfer'] ?? false),
            transferReason: $data['transfer_reason'] ?? null,
            confidence: (float) ($data['confidence'] ?? 1.0),
            agentConversationId: $data['agent_conversation_id'] ?? null,
            metadata: $data['metadata'] ?? null,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'reply' => $this->reply,
            'reply_type' => $this->replyType,
            'rich_content' => $this->richContent,
            'should_transfer' => $this->shouldTransfer,
            'transfer_reason' => $this->transferReason,
            'confidence' => $this->confidence,
            'agent_conversation_id' => $this->agentConversationId,
            'metadata' => $this->metadata,
        ];
    }
}
