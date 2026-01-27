<?php

namespace HuiZhiDa\Gateway\Applications\Services;

use HuiZhiDa\Core\Application\Services\ConversationApplicationService;
use HuiZhiDa\Core\Domain\Channel\Models\Channel;
use HuiZhiDa\Core\Domain\Channel\Repositories\ChannelRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\DTO\ChannelMessage;
use HuiZhiDa\Core\Domain\Conversation\DTO\Events\ConversationEvent;
use HuiZhiDa\Core\Domain\Conversation\Models\Conversation;
use HuiZhiDa\Gateway\Infrastructure\Adapters\AdapterFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RedJasmine\Support\Application\ApplicationService;
use Illuminate\Http\JsonResponse;

class GatewayApplicationService extends ApplicationService
{

    public function __construct(
        protected ChannelRepositoryInterface $channelRepository,
        protected AdapterFactory $channelAdapterFactory,
        protected ConversationApplicationService $conversationApplicationService,

    ) {
    }


    public function health(Request $request, $id)
    {
        $channelModel  = $this->channelRepository->find($id);
        $channelConfig = $channelModel->config; // 实际应从数据库读取
        $channel       = $channelModel->channel;
        $adapter       = $this->channelAdapterFactory->get($channel, $channelConfig);
        return $adapter->health($request);
    }

    public function callback(Request $request, $id) : JsonResponse
    {
        Log::withContext(['channelId' => $id]);

        try {
            // 1. 获取渠道适配器

            $channelModel = $this->channelRepository->find($id);
            $channel      = $channelModel->channel;

            $adapter = $this->channelAdapterFactory->get($channelModel->channel, $channelModel->config);

            // 2. 验证签名
            if (!$adapter->verifySignature($request)) {
                Log::warning('Signature verification failed');
                return response()->json(['error' => 'invalid signature'], 403);
            }

            // 解析消息
            $messages = $adapter->parseMessages($request);
            // 对消息进行分组

            collect($messages)->each(function (ChannelMessage $channelMessage) use ($channelModel) {
                $channelMessage->channelId = $channelModel->id;
                $channelMessage->appId     = $channelModel->app_id;
            });
            $messageGroups = $this->groupMessagesByUser($messages);

            // 按分组处理消息
            foreach ($messageGroups as $messages) {
                $this->handleMessages($channelModel, [...$messages]);
            }

            // 9. 快速响应渠道
            $response = $adapter->getSuccessResponse();

            Log::info('Callback processed successfully', [
                'channel'    => $channel,
                'channel_id' => $id,
            ]);

            return response()->json($response, 200);
        } catch (InvalidArgumentException $e) {
            throw $e;

            Log::error('Unsupported channel', [
                'channel' => $channel,
                'error'   => $e->getMessage(),
            ]);
            return response()->json(['error' => 'unsupported channel'], 400);
        } catch (Exception $e) {
            throw $e;
            Log::error('Callback processing failed', [
                'channel'    => $channel,
                'channel_id' => $id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'internal server error'], 500);
        }

    }

    /**
     * 对消息进行分组
     *
     * @param  array  $messages
     *
     * @return Collection
     */
    protected function groupMessagesByUser(array $messages) : Collection
    {
        // - 按 渠道ID,渠道应用ID,用户类型、用户ID, 渠道会话ID 分组
        return collect($messages)->groupBy(function (ChannelMessage $channelMessage) {
            return implode('|', [
                $channelMessage->channelId,
                $channelMessage->appId,
                $channelMessage->sender->getType(),
                $channelMessage->sender->getID(),
                $channelMessage->channelConversationId,
            ]);
        });


    }


    /**
     *
     * @param  Channel  $channel
     * @param  ChannelMessage[]  $messages
     *
     * @return void
     */
    protected function handleMessages(Channel $channel, array $messages) : void
    {
        /**
         * @var Conversation $conversation
         */
        $conversation = null;

        foreach ($messages as $message) {

            // 设置渠道和应用信息
            $message->appId     = $channel->app_id;
            $message->channelId = (int) $channel->id;
            // 如果没有渠道会话ID 把渠道会话ID
            $message->channelConversationId = $message->channelConversationId ?? null;
            if (!$conversation) {
                $conversation = $this->conversationApplicationService->getOrCreate($message);
            }
            // 获取 系统会话ID
            $message->conversationId = $conversation->conversation_id;
        }

        // 7. 存储会话待处理消息
        $this->conversationApplicationService->savePendingMessages($conversation->conversation_id, $messages);

        // 8. 第二步：推送事件消息到队列，包含会话ID
        $this->conversationApplicationService->triggerEvent(new ConversationEvent($conversation->conversation_id));


    }

    protected function createConversation(ChannelMessage $message)
    {
        // 5. 获取或创建会话
        $conversation = $this->conversationService->getOrCreate($message);
    }

}