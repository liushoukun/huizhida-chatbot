<?php

namespace HuiZhiDa\Gateway\Infrastructure\Adapters;

use Illuminate\Http\Request;
use HuiZhiDa\Gateway\Domain\Contracts\ChannelAdapterInterface;
use HuiZhiDa\Gateway\Domain\Models\Message;
use HuiZhiDa\Gateway\Domain\Models\UserInfo;
use HuiZhiDa\Gateway\Domain\Models\MessageContent;

class WecomAdapter implements ChannelAdapterInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function verifySignature(Request $request): bool
    {
        // TODO: 实现企业微信签名验证
        $token = $this->config['token'] ?? '';
        $encodingAesKey = $this->config['encoding_aes_key'] ?? '';
        
        // 简化实现，实际需要根据企业微信文档实现
        return true;
    }

    public function parseMessage(string $rawData): Message
    {
        $data = json_decode($rawData, true);
        
        $message = Message::createIncoming();
        $message->messageId = $data['MsgId'] ?? uniqid('msg_', true);
        $message->channelMessageId = $data['MsgId'] ?? '';
        $message->messageType = $this->mapMessageType($data['MsgType'] ?? 'text');
        $message->timestamp = $data['CreateTime'] ?? time();
        $message->rawData = $rawData;

        // 解析用户信息
        $message->user = new UserInfo();
        $message->user->channelUserId = $data['FromUserName'] ?? '';
        $message->user->nickname = $data['NickName'] ?? '';

        // 解析消息内容
        $message->content = new MessageContent();
        if ($message->messageType === 'text') {
            $message->content->text = $data['Content'] ?? '';
        } elseif (in_array($message->messageType, ['image', 'voice', 'video', 'file'])) {
            $message->content->mediaUrl = $data['MediaId'] ?? '';
            $message->content->mediaType = $message->messageType;
        }

        return $message;
    }

    public function convertToChannelFormat(Message $message): array
    {
        if (!$message->isOutgoing()) {
            throw new \InvalidArgumentException('Message must be outgoing type');
        }

        $data = [
            'msgtype' => $message->replyType === 'text' ? 'text' : 'news',
        ];

        if ($message->replyType === 'text') {
            $data['text'] = [
                'content' => $message->reply,
            ];
        } else {
            $data['news'] = $message->richContent ?? [];
        }

        return $data;
    }

    public function sendMessage(Message $message): void
    {
        // TODO: 实现企业微信消息发送
        $format = $this->convertToChannelFormat($message);
        
        // 实际需要调用企业微信 API
        // 这里只是示例
    }

    public function transferToQueue(string $conversationId, string $priority = 'normal'): void
    {
        // TODO: 实现转接到客服队列
    }

    public function transferToSpecific(string $conversationId, string $servicerId, string $priority = 'normal'): void
    {
        // TODO: 实现转接到指定客服
    }

    public function getSuccessResponse(): array
    {
        return [
            'errcode' => 0,
            'errmsg' => 'ok',
        ];
    }

    protected function mapMessageType(string $wecomType): string
    {
        $map = [
            'text' => 'text',
            'image' => 'image',
            'voice' => 'voice',
            'video' => 'video',
            'file' => 'file',
            'location' => 'location',
            'link' => 'link',
        ];

        return $map[$wecomType] ?? 'text';
    }
}
