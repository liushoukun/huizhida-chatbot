<?php

namespace HuiZhiDa\Engine\Channel\UI\Consoles\Commands;

use Exception;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use HuiZhiDa\Engine\Channel\Application\Services\GatewayApplicationService;
use HuiZhiDa\Engine\Channel\Domain\DTO\CallbackPayload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CallbackQueueCommand extends Command
{
    protected $signature   = 'huizhida:callback:queue';
    protected $description = 'Consume callback queue: fetch messages and handle (sync_msg, download media, handleMessages)';

    public function __construct(
        protected ConversationQueueInterface $mq,
        protected GatewayApplicationService $gatewayApplicationService,
    ) {
        parent::__construct();
    }

    public function handle() : int
    {
        $this->info('回调队列开始消费');
        $this->info(date('Y-m-d H:i:s'));

        $this->mq->subscribe(ConversationQueueType::Callback, function ($data) {
            $this->info('收到回调消息'.json_encode($data));
            try {
                $payload = CallbackPayload::from($data);
                $this->gatewayApplicationService->processCallbackJob($payload);
            } catch (Exception $e) {
                Log::error('Callback job failed', [
                    'data'  => $data,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                throw $e;
            }
        });

        return 0;
    }
}
