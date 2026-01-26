<?php

namespace HuiZhiDa\AgentProcessor\Application\Services;

use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationStatus;
use Illuminate\Support\Facades\Log;

/**
 * 预校验服务
 * 在调用智能体之前进行规则预判断
 */
class PreCheckService
{
    public const ACTION_CALL_AGENT     = 'call_agent';
    public const ACTION_TRANSFER_HUMAN = 'transfer_human';
    public const ACTION_SKIP           = 'skip';

    protected array $transferKeywords;
    protected bool  $vipDirectTransfer;

    public function __construct()
    {
        $config                  = config('agent-processor.pre_check', []);
        $keywords                = $config['transfer_keywords'] ?? '转人工,人工客服,找人工,真人客服,投诉';
        $this->transferKeywords  = is_string($keywords) ? explode(',', $keywords) : $keywords;
        $this->vipDirectTransfer = $config['vip_direct_transfer'] ?? false;
    }

    /**
     * 执行预校验
     *
     * @param  ChannelMessage[]  $messages  消息组数据
     * @param  ConversationData  $conversation  会话数据
     *
     * @return CheckResult
     */
    public function check(array $messages, ConversationData $conversation) : CheckResult
    {
        // 1. 检测是否需要跳过
        $skipResult = $this->checkSkip($conversation);
        if ($skipResult !== null) {
            return $skipResult;
        }

        // 2. 检测是否需要转人工（匹配转人工内部不同策略）
        $transferResult = $this->checkTransferHuman($messages, $conversation);
        if ($transferResult !== null) {
            return $transferResult;
        }

        return CheckResult::from([
            'actionType' => ActionType::Continue
        ]);

    }

    /**
     * 检测是否需要跳过
     *
     * @param  ConversationData  $conversation  会话数据
     *
     * @return array|null ['action' => string, 'reason' => string] 如果需要跳过则返回结果，否则返回null
     */
    protected function checkSkip(ConversationData $conversation) : ?CheckResult
    {

        if (in_array($conversation->status, [
            ConversationStatus::HumanQueuing,
            ConversationStatus::Human,
            ConversationStatus::Closed,
        ])) {

            Log::info('PreCheck: Conversation already transferred', [
                'conversation_id' => $conversation->conversationId,
                'status'          => $conversation->status,
            ]);
            return CheckResult::from([
                'actionType' => ActionType::Ignore
            ]);
        }


        return null;
    }

    /**
     * 检测是否需要转人工（匹配转人工内部不同策略）
     *
     * @param  ChannelMessage[]  $messages  消息组数据
     * @param  ConversationData  $conversation  会话数据
     *
     * @return array|null ['action' => string, 'reason' => string, 'strategy' => string] 如果需要转人工则返回结果，否则返回null
     */
    protected function checkTransferHuman(array $messages, ConversationData $conversation) : ?CheckResult
    {
        // 策略1: 关键词匹配转人工
        $keywordResult = $this->checkTransferByKeyword($messages, $conversation);
        if ($keywordResult !== null) {
            return $keywordResult;
        }

        return null;
    }

    /**
     * 策略1: 关键词匹配转人工
     *
     * @param  ChannelMessage[]  $messages  消息组数据
     * @param  ConversationData  $conversation  会话数据
     *
     * @return array|null
     */
    protected function checkTransferByKeyword(array $messages, ConversationData $conversation) : ?CheckResult
    {
        // 遍历消息组，检查是否有转人工关键词
        foreach ($messages as $message) {
            $text = $this->extractText($message);
            if ($text && $this->hasTransferKeyword($text)) {
                Log::info("预校验 符合转人工关键字");
                return CheckResult::from([
                    'actionType' => ActionType::TransferHuman
                ]);
            }
        }

        return null;
    }


    /**
     * 从消息中提取文本内容
     *
     * @param  ChannelMessage|array  $message  消息对象或数组
     *
     * @return string|null
     */
    protected function extractText($message) : ?string
    {
        // 如果是 ChannelMessage 对象
        if (is_object($message) && isset($message->content)) {
            if (is_array($message->content) && isset($message->content['text'])) {
                return $message->content['text'];
            }
            if (is_string($message->content)) {
                return $message->content;
            }
        }

        // 如果是数组
        if (is_array($message)) {
            if (isset($message['content']['text'])) {
                return $message['content']['text'];
            }
            if (isset($message['content']) && is_string($message['content'])) {
                return $message['content'];
            }
        }

        return null;
    }

    /**
     * 检查是否包含转人工关键词
     */
    protected function hasTransferKeyword(string $text) : bool
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
