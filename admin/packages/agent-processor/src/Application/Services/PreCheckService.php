<?php

namespace HuiZhiDa\AgentProcessor\Application\Services;

use Illuminate\Support\Facades\Log;

/**
 * 预校验服务
 * 在调用智能体之前进行规则预判断
 */
class PreCheckService
{
    public const ACTION_CALL_AGENT = 'call_agent';
    public const ACTION_TRANSFER_HUMAN = 'transfer_human';
    public const ACTION_SKIP = 'skip';

    protected array $transferKeywords;
    protected bool $vipDirectTransfer;

    public function __construct()
    {
        $config = config('agent-processor.pre_check', []);
        $keywords = $config['transfer_keywords'] ?? '转人工,人工客服,找人工,真人客服,投诉';
        $this->transferKeywords = is_string($keywords) ? explode(',', $keywords) : $keywords;
        $this->vipDirectTransfer = $config['vip_direct_transfer'] ?? false;
    }

    /**
     * 执行预校验
     *
     * @param array $message 消息数据
     * @param array $conversation 会话数据
     * @return array ['action' => string, 'reason' => string|null]
     */
    public function check(array $message, array $conversation): array
    {
        // 1. 检查会话是否已转人工
        if (isset($conversation['status']) && in_array($conversation['status'], ['transferred', 'pending_human'])) {
            Log::info('PreCheck: Conversation already transferred', [
                'conversation_id' => $conversation['conversation_id'] ?? null,
                'status' => $conversation['status'],
            ]);
            return [
                'action' => self::ACTION_SKIP,
                'reason' => 'already_transferred',
            ];
        }

        // 2. 关键词匹配，直接转人工
        $text = $this->extractText($message);
        if ($text && $this->hasTransferKeyword($text)) {
            Log::info('PreCheck: Transfer keyword matched', [
                'conversation_id' => $conversation['conversation_id'] ?? null,
                'text' => $text,
            ]);
            return [
                'action' => self::ACTION_TRANSFER_HUMAN,
                'reason' => 'keyword_match',
            ];
        }

        // 3. VIP用户直接转人工策略（可配置）
        if (isset($conversation['is_vip']) && $conversation['is_vip'] && $this->vipDirectTransfer) {
            Log::info('PreCheck: VIP direct transfer', [
                'conversation_id' => $conversation['conversation_id'] ?? null,
            ]);
            return [
                'action' => self::ACTION_TRANSFER_HUMAN,
                'reason' => 'vip_policy',
            ];
        }

        // 4. 正常调用智能体
        return [
            'action' => self::ACTION_CALL_AGENT,
            'reason' => null,
        ];
    }

    /**
     * 从消息中提取文本内容
     */
    protected function extractText(array $message): ?string
    {
        if (isset($message['content']['text'])) {
            return $message['content']['text'];
        }
        if (isset($message['content']) && is_string($message['content'])) {
            return $message['content'];
        }
        return null;
    }

    /**
     * 检查是否包含转人工关键词
     */
    protected function hasTransferKeyword(string $text): bool
    {
        foreach ($this->transferKeywords as $keyword) {
            $keyword = trim($keyword);
            if (!empty($keyword) && mb_strpos($text, $keyword) !== false) {
                return true;
            }
        }
        return false;
    }
}
