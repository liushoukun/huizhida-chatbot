<?php

namespace HuiZhiDa\Gateway\UI\Consoles\Commands;

use Exception;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use HuiZhiDa\Gateway\Application\Services\GatewayApplicationService;
use HuiZhiDa\Gateway\Domain\DTO\CallbackPayload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CallbackQueueCommand extends Command
{
    protected $signature   = 'gateway:callback:consume';
    protected $description = 'Consume callback queue: fetch messages and handle (sync_msg, download media, handleMessages)';

    public function __construct(
        protected ConversationQueueInterface $mq,
        protected GatewayApplicationService $gatewayApplicationService,
    ) {
        parent::__construct();
    }

    public function handle() : int
    {
        $this->info('Callback queue consumer started');

        $this->mq->subscribe(ConversationQueueType::Callback, function ($data) {
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
