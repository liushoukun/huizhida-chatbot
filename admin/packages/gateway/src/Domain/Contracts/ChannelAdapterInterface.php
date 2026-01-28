<?php

namespace HuiZhiDa\Gateway\Domain\Contracts;

use HuiZhiDa\Processor\Domain\Data\AgentChatResponse;
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
