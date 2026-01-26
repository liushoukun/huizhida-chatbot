<?php

namespace HuiZhiDa\Gateway\Console\Commands;

use Exception;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Gateway\Infrastructure\Adapters\AdapterFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class TransferExecutorCommand extends Command
{
    protected $signature   = 'gateway:transfer-executor';
    protected $description = 'Start transfer executor consumer';

    public function __construct(
        protected AdapterFactory $adapterFactory,
        protected ConversationApplicationService $conversationApplicationService,
        protected ConversationQueueInterface $mq
    ) {
        parent::__construct();
    }

    public function handle() : int
    {
        $this->info('Transfer executor consumer started');

        $queueName = config('gateway.queue.transfer_queue', 'transfer_requests');

        $this->mq->subscribe($queueName, function ($data) {
            $event = ConversationEvent::from($data);
            $this->handleTransfer($event);
        });

        return 0;
    }

    protected function handleTransfer(ConversationEvent $event) : void
    {
        try {
            $conversationId = $event->conversationId;

            // 获取会话信息以获取channel_id
            $conversation = $this->conversationApplicationService->get($conversationId);
            if (!$conversation) {
                throw new InvalidArgumentException('Conversation not found');
            }


            // 获取渠道适配器
            $channelId = $conversation['channel_id'] ?? null;
            if (!$channelId) {
                throw new InvalidArgumentException('Channel ID not found in conversation');
            }

            // 从数据库获取channel信息
            $channel = DB::table('channels')->where('id', $channelId)->first();
            if (!$channel) {
                throw new InvalidArgumentException('Channel not found');
            }

            $channelConfig = $channel->config ?? [];
            $adapter       = $this->adapterFactory->get($channel->channel, $channelConfig);

            // 执行转接
            if ($mode === 'specific' && $specificServicer) {
                $adapter->transferToSpecific(
                    $conversationId,
                    $specificServicer,
                    $priority
                );
            } else {
                $adapter->transferToQueue($conversationId, $priority);
            }

            $this->info('Transfer executed successfully', [
                'conversation_id' => $conversationId,
                'channel'         => $channel->channel,
            ]);
        } catch (Exception $e) {
            Log::error('Handle transfer failed', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
            $this->mq->nack($data);
        }
    }
}
