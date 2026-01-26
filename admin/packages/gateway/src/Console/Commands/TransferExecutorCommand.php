<?php

namespace HuiZhiDa\Gateway\Console\Commands;

use Exception;
use HuiZhiDa\Core\Application\Services\ChannelApplicationService;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
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
        protected ChannelApplicationService $channelApplicationService,
        protected ConversationQueueInterface $mq
    ) {
        parent::__construct();
    }

    public function handle() : int
    {
        $this->info('Transfer executor consumer started');


        $this->mq->subscribe(ConversationQueueType::Transfer, function ($data) {
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


            $channelId = $conversation->channelId;
            $channel = $this->channelApplicationService->findByChannelId($channelId);


            $channelConfig = $channel->config ?? [];
            $adapter       = $this->adapterFactory->get($channel->channel, $channelConfig);
            $adapter->transferToHumanQueuing($conversation);

            $this->info('Transfer executed successfully');
        } catch (\Throwable $e) {

            Log::error('Handle transfer failed', [
                'error' => $e->getMessage(),
            ]);

        }
    }
}
