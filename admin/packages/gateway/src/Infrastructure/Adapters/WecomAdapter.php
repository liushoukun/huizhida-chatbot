<?php

namespace HuiZhiDa\Gateway\Infrastructure\Adapters;

use Illuminate\Http\Request;
use HuiZhiDa\Gateway\Domain\Contracts\ChannelAdapterInterface;
use HuiZhiDa\Core\Domain\Message\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Message\DTO\UserInfo;
use HuiZhiDa\Core\Domain\Message\DTO\Contents\TextContent;
use HuiZhiDa\Core\Domain\Message\DTO\Contents\ImageContent;
use HuiZhiDa\Core\Domain\Message\Enums\MessageType;
use HuiZhiDa\Core\Domain\Message\Enums\ContentType;
use HuiZhiDa\Core\Domain\Message\Enums\UserType;

class WecomAdapter implements ChannelAdapterInterface
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function verifySignature(Request $request) : bool
    {
        // TODO: 实现企业微信签名验证
        $token          = $this->config['token'] ?? '';
        $encodingAesKey = $this->config['encoding_aes_key'] ?? '';

        // 简化实现，实际需要根据企业微信文档实现
        return true;
    }

    public function parseMessage(Request $request) : ChannelMessage
    {
        $rawData = $request->getContent();
        $data    = json_decode($rawData, true);

        $message                   = new ChannelMessage();
        $message->messageId        = $data['MsgId'] ?? uniqid('msg_', true);
        $message->channelMessageId = $data['MsgId'] ?? '';
        $message->messageType      = $this->mapMessageType($data['MsgType'] ?? 'text');
        $message->contentType      = $this->mapContentType($data['MsgType'] ?? 'text');
        $message->timestamp        = $data['CreateTime'] ?? time();
        $message->rawData          = $rawData;

        // 解析用户信息
        $message->sender           = new UserInfo();
        $message->sender->type     = UserType::User;
        $message->sender->id       = $data['FromUserName'] ?? '';
        $message->sender->nickname = $data['NickName'] ?? '';

        // 解析消息内容
        $msgType = $data['MsgType'] ?? 'text';
        if ($msgType === 'text') {
            $content          = new TextContent();
            $content->content = $data['Content'] ?? '';
            $message->content = $content;
        } elseif (in_array($msgType, ['image', 'voice', 'video', 'file'])) {
            $content          = new ImageContent();
            $content->url     = $data['MediaId'] ?? '';
            $message->content = $content;
        }

        return $message;
    }

    public function convertToChannelFormat(ChannelMessage $message) : array
    {
        $data = [
            'msgtype' => $message->contentType === ContentType::Text ? 'text' : 'news',
        ];

        if ($message->contentType === ContentType::Text && $message->content instanceof TextContent) {
            $data['text'] = [
                'content' => $message->content->content,
            ];
        } else {
            // 处理其他类型的内容
            $data['news'] = [];
        }

        return $data;
    }

    public function sendMessage(ChannelMessage $message) : void
    {
        // TODO: 实现企业微信消息发送
        $format = $this->convertToChannelFormat($message);

        // 实际需要调用企业微信 API
        // 这里只是示例
    }

    public function transferToQueue(string $conversationId, string $priority = 'normal') : void
    {
        // TODO: 实现转接到客服队列
    }

    public function transferToSpecific(string $conversationId, string $servicerId, string $priority = 'normal') : void
    {
        // TODO: 实现转接到指定客服
    }

    public function getSuccessResponse() : array
    {
        return [
            'errcode' => 0,
            'errmsg'  => 'ok',
        ];
    }

    protected function mapMessageType(string $wecomType) : MessageType
    {
        $map = [
            'text'     => MessageType::Question,
            'image'    => MessageType::Question,
            'voice'    => MessageType::Question,
            'video'    => MessageType::Question,
            'file'     => MessageType::Question,
            'location' => MessageType::Question,
            'link'     => MessageType::Question,
            'event'    => MessageType::Event,
        ];

        return $map[$wecomType] ?? MessageType::Question;
    }

    protected function mapContentType(string $wecomType) : ContentType
    {
        $map = [
            'text'  => ContentType::Text,
            'image' => ContentType::Image,
            'voice' => ContentType::Voice,
            'video' => ContentType::Video,
            'file'  => ContentType::File,
        ];

        return $map[$wecomType] ?? ContentType::Text;
    }
}
