<?php

namespace HuiZhiDa\Gateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Gateway\Infrastructure\Adapters\AdapterFactory;
use HuiZhiDa\Gateway\Application\Services\ConversationService;
use HuiZhiDa\Gateway\Domain\Contracts\MessageQueueInterface;
use HuiZhiDa\Gateway\Domain\Models\Message;

class TransferExecutorCommand extends Command
{
    protected $signature = 'gateway:transfer-executor';
    protected $description = 'Start transfer executor consumer';

    public function __construct(
        protected AdapterFactory $adapterFactory,
        protected ConversationService $conversationService,
        protected MessageQueueInterface $mq
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Transfer executor consumer started');

        $queueName = config('gateway.queue.transfer_queue', 'transfer_requests');

        $this->mq->subscribe($queueName, function ($data) {
            $this->handleTransfer($data);
        });

        return 0;
    }

    protected function handleTransfer(array $data): void
    {
        try {
            $message = Message::fromArray($data);

            if (!$message->isTransfer()) {
                throw new \InvalidArgumentException('Message must be transfer type');
            }

            // 更新会话状态
            $this->conversationService->updateStatus(
                $message->conversationId,
                'pending_human',
                $message->reason,
                $message->source
            );

            // 获取渠道适配器
            $channelConfig = [];
            $adapter = $this->adapterFactory->get($message->channel, $channelConfig);

            // 执行转接
            if ($message->mode === 'specific' && $message->specificServicer) {
                $adapter->transferToSpecific(
                    $message->conversationId,
                    $message->specificServicer,
                    $message->priority ?? 'normal'
                );
            } else {
                $adapter->transferToQueue($message->conversationId, $message->priority ?? 'normal');
            }

            $this->info('Transfer executed successfully', [
                'conversation_id' => $message->conversationId,
                'channel' => $message->channel,
            ]);
        } catch (\Exception $e) {
            Log::error('Handle transfer failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            $this->mq->nack($data);
        }
    }
}
