<?php

namespace HuiZhiDa\Engine\Channel\Domain\Contracts;

use HuiZhiDa\Engine\Channel\Domain\DTO\CallbackPayload;
use HuiZhiDa\Engine\Agent\Domain\Data\AgentChatResponse;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationOutputQueue;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationData;
use Illuminate\Http\Request;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use Illuminate\Http\Response;

interface ChannelAdapterInterface
{

    public function health(Request $request);

    /**
     * 验证签名
     */
    public function verifySignature(Request $request) : bool;

    /**
     * 从回调请求中提取最小载荷（仅解密 + 提取），不入队时返回 null
     *
     * @return CallbackPayload|null 含 channel_id 与渠道 payload，或 null 表示走同步逻辑
     */
    public function extractCallbackPayload(Request $request, string $channelId) : ?CallbackPayload;

    /**
     * 根据 CallbackPayload 拉取并解析消息（含 sync_msg、下载媒体等），仅 Worker 调用
     *
     * @return ChannelMessage[]
     */
    public function fetchAndParseMessages(CallbackPayload $payload) : array;

    /**
     * 解析渠道消息格式，转换为统一格式
     *
     * @param  Request  $request
     *
     * @return ChannelMessage[]
     */
    public function parseMessages(Request $request) : array;

    /**
     * 将统一格式转换为渠道格式
     */
    public function convertToChannelFormat(ChannelMessage $message) : array;

    /**
     * 发送消息到渠道
     */
    public function sendMessages(ConversationOutputQueue $conversationOutputQueue) : void;


    public function transferToHumanQueuing(ConversationData $conversation) : void;

    /**
     * 获取成功响应
     */
    public function getSuccessResponse() : array;
}
