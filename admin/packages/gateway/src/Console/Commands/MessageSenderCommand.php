<?php

namespace HuiZhiDa\Gateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Gateway\Infrastructure\Adapters\AdapterFactory;
use HuiZhiDa\Gateway\Application\Services\MessageService;
use HuiZhiDa\Gateway\Domain\Contracts\MessageQueueInterface;
use HuiZhiDa\Message\Domain\DTO\ChannelMessage;
use HuiZhiDa\Message\Domain\Enums\MessageType;

class MessageSenderCommand extends Command
{
    protected $signature = 'gateway:message-sender';
    protected $description = 'Start message sender consumer';

    public function __construct(
        protected AdapterFactory $adapterFactory,
        protected MessageService $messageService,
        protected MessageQueueInterface $mq
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Message sender consumer started');

        $queueName = config('gateway.queue.outgoing_queue', 'outgoing_messages');

        $this->mq->subscribe($queueName, function ($data) {
            $this->handleMessage($data);
        });

        return 0;
    }

    protected function handleMessage(array $data): void
    {
        try {
            // 从数组创建ChannelMessage（使用Data类的from方法）
            $message = ChannelMessage::from($data);

            // 验证消息类型（发送的消息应该是Answer类型）
            if ($message->messageType !== MessageType::Answer) {
                throw new \InvalidArgumentException('Message must be Answer type for sending');
            }

            // 获取渠道适配器
            // TODO: 从数据库获取渠道配置，需要根据channelId获取
            $channelConfig = [];
            // 需要从channelId获取channel类型
            $channel = $this->getChannelType($message->channelId);
            $adapter = $this->adapterFactory->get($channel, $channelConfig);

            // 发送消息
            $adapter->sendMessage($message);

            // 更新消息状态
            try {
                $this->messageService->updateStatus($message->messageId ?? '', 'sent');
            } catch (\Exception $e) {
                Log::warning('Update message status failed', [
                    'message_id' => $message->messageId,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->info('Message sent successfully', [
                'message_id' => $message->messageId,
                'conversation_id' => $message->conversationId,
                'channel_id' => $message->channelId,
            ]);
        } catch (\Exception $e) {
            Log::error('Handle message failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            $this->mq->nack($data);
        }
    }

    /**
     * 根据channelId获取channel类型
     * TODO: 从数据库查询
     */
    protected function getChannelType(?string $channelId): string
    {
        // TODO: 从数据库查询channels表获取channel类型
        return 'api'; // 默认返回api
    }
}
