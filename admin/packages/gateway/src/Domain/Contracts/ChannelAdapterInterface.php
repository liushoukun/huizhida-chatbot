<?php

namespace HuiZhiDa\Gateway\Domain\Contracts;

use Illuminate\Http\Request;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;

interface ChannelAdapterInterface
{
    /**
     * 验证签名
     */
    public function verifySignature(Request $request): bool;

    /**
     * 解析渠道消息格式，转换为统一格式
     */
    public function parseMessage(Request $request): ChannelMessage;

    /**
     * 将统一格式转换为渠道格式
     */
    public function convertToChannelFormat(ChannelMessage $message): array;

    /**
     * 发送消息到渠道
     */
    public function sendMessage(ChannelMessage $message): void;

    /**
     * 转接到客服队列
     */
    public function transferToQueue(string $conversationId, string $priority = 'normal'): void;

    /**
     * 转接到指定客服
     */
    public function transferToSpecific(string $conversationId, string $servicerId, string $priority = 'normal'): void;

    /**
     * 获取成功响应
     */
    public function getSuccessResponse(): array;
}
