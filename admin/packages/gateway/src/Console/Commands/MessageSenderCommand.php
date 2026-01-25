<?php

namespace HuiZhiDa\Gateway\Console\Commands;

use Exception;
use HuiZhiDa\AgentProcessor\Domain\Data\AgentChatResponse;
use HuiZhiDa\Core\Application\Services\ChannelApplicationService;
use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationAnswerData;
use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationQueueType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Gateway\Infrastructure\Adapters\AdapterFactory;
use HuiZhiDa\Core\Domain\Conversation\Services\MessageService;
use HuiZhiDa\Core\Domain\Conversation\Contracts\ConversationQueueInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use InvalidArgumentException;
use RedJasmine\Payment\Domain\Models\ChannelApp;
use RedJasmine\Support\Domain\Queries\FindQuery;

class MessageSenderCommand extends Command
{
    protected $signature   = 'gateway:message-sender';
    protected $description = 'Start message sender consumer';

    public function __construct(
        protected AdapterFactory $adapterFactory,
        protected MessageService $messageService,
        protected ConversationApplicationService $conversationApplicationService,
        protected ChannelApplicationService $channelApplicationService,
        protected ConversationQueueInterface $mq
    ) {
        parent::__construct();
    }

    public function handle() : int
    {
        $this->info('Message sender consumer started');


        $this->mq->subscribe(ConversationQueueType::Sending, function ($data) {


            $conversationAnswer = ConversationAnswerData::from($data);

            $this->handleMessage($conversationAnswer);
        });

        return 0;
    }

    protected function handleMessage(ConversationAnswerData $conversationAnswer) : void
    {
        try {


            $channel      = $this->channelApplicationService->find(FindQuery::make($conversationAnswer->channelId));


            $adapter = $this->adapterFactory->get($channel->channel, $channel->config);


            // 发送消息
            $adapter->sendMessages($conversationAnswer);
            Log::info('发送结束');
            // 更新消息状态
            // try {
            //     $this->messageService->updateStatus($message->messageId ?? '', 'sent');
            // } catch (Exception $e) {
            //     Log::warning('Update message status failed', [
            //         'message_id' => $message->messageId,
            //         'error'      => $e->getMessage(),
            //     ]);
            // }

            // $this->info('Message sent successfully', [
            //     'message_id'      => $message->messageId,
            //     'conversation_id' => $message->conversationId,
            //     'channel_id'      => $message->channelId,
            // ]);
        } catch (Exception $e) {
            throw $e;
            // Log::error('Handle message failed', [
            //     'error' => $e->getMessage(),
            //     'data'  => $data,
            // ]);
            //$this->mq->nack($data);
        }
    }

    /**
     * 根据channelId获取channel类型
     * TODO: 从数据库查询
     */
    protected function getChannelType(?string $channelId) : string
    {
        // TODO: 从数据库查询channels表获取channel类型
        return 'api'; // 默认返回api
    }
}
