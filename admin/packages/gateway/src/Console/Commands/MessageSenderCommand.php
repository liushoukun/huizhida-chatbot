<?php

namespace HuiZhiDa\Gateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Gateway\Infrastructure\Adapters\AdapterFactory;
use HuiZhiDa\Gateway\Application\Services\MessageService;
use HuiZhiDa\Gateway\Domain\Contracts\MessageQueueInterface;
use HuiZhiDa\Gateway\Domain\Models\Message;

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
            $message = Message::fromArray($data);

            if (!$message->isOutgoing()) {
                throw new \InvalidArgumentException('Message must be outgoing type');
            }

            // 获取渠道适配器
            // TODO: 从数据库获取渠道配置
            $channelConfig = [];
            $adapter = $this->adapterFactory->get($message->channel, $channelConfig);

            // 发送消息
            $adapter->sendMessage($message);

            // 更新消息状态
            try {
                $this->messageService->updateStatus($message->messageId, 'sent');
            } catch (\Exception $e) {
                Log::warning('Update message status failed', [
                    'message_id' => $message->messageId,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->info('Message sent successfully', [
                'message_id' => $message->messageId,
                'conversation_id' => $message->conversationId,
                'channel' => $message->channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Handle message failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            $this->mq->nack($data);
        }
    }
}
