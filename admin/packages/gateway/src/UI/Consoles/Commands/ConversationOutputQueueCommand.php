<?php

namespace HuiZhiDa\Gateway\UI\Consoles\Commands;

use Exception;
use HuiZhiDa\Core\Application\Services\ChannelApplicationService;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationOutputQueue;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use HuiZhiDa\Gateway\Infrastructure\Adapters\AdapterFactory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use RedJasmine\Payment\Domain\Models\ChannelApp;
use RedJasmine\Support\Domain\Queries\FindQuery;

class ConversationOutputQueueCommand extends Command
{
    protected $signature   = 'conversation:outputs:consume';
    protected $description = 'Start message sender consumer';

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
        $this->info('Message sender consumer started');


        $this->mq->subscribe(ConversationQueueType::Outputs, function ($data) {

            $this->info('处理MQ',$data);
            $conversationOutputQueue = ConversationOutputQueue::from($data);

            $this->handleOutputQueue($conversationOutputQueue);
        });

        return 0;
    }

    protected function handleOutputQueue(ConversationOutputQueue $conversationOutputQueue) : void
    {
        try {

            //
            $channel = $this->channelApplicationService->find(FindQuery::make($conversationOutputQueue->channelId));

            $adapter = $this->adapterFactory->get($channel->channel, $channel->config);

            // 发送消息
            $adapter->sendMessages($conversationOutputQueue);
            Log::info('发送结束');

        } catch (Exception $e) {
            throw $e;
            // Log::error('Handle message failed', [
            //     'error' => $e->getMessage(),
            //     'data'  => $data,
            // ]);
            //$this->mq->nack($data);
        }
    }


}
