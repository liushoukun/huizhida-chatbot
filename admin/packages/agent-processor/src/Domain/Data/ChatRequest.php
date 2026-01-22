<?php

namespace HuiZhiDa\AgentProcessor\Domain\Data;

/**
 * 聊天请求数据对象
 */
readonly class ChatRequest
{
    public function __construct(
        public string $conversationId,
        public ?string $agentConversationId = null,
        public array $messages = [],
        public array $history = [],
        public array $context = [],
        public array $userInfo = [],
        public ?int $timestamp = null,
    ) {
    }

    /**
     * 从数组创建实例
     */
    public static function fromArray(array $data): self
    {
        return new self(
            conversationId: $data['conversation_id'] ?? '',
            agentConversationId: $data['agent_conversation_id'] ?? null,
            messages: $data['messages'] ?? [],
            history: $data['history'] ?? [],
            context: $data['context'] ?? [],
            userInfo: $data['user_info'] ?? [],
            timestamp: $data['timestamp'] ?? time(),
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'conversation_id' => $this->conversationId,
            'agent_conversation_id' => $this->agentConversationId,
            'messages' => $this->messages,
            'history' => $this->history,
            'context' => $this->context,
            'user_info' => $this->userInfo,
            'timestamp' => $this->timestamp,
        ];
    }
}
