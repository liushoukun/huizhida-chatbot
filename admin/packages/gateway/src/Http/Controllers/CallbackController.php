<?php

namespace HuiZhiDa\Gateway\Http\Controllers;

use Exception;
use HuiZhiDa\Core\Domain\Channel\Repositories\ChannelRepositoryInterface;
use HuiZhiDa\Core\Domain\Conversation\Services\ConversationService;
use HuiZhiDa\Core\Domain\Conversation\Services\MessageService;
use HuiZhiDa\Gateway\Infrastructure\Adapters\AdapterFactory;
use HuiZhiDa\Core\Domain\Conversation\DTO\ConversationEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CallbackController
{
    public function __construct(
        protected AdapterFactory $adapterFactory,
        protected ConversationService $conversationService,
        protected MessageService $messageService,
        protected ChannelRepositoryInterface $channelRepository,
    ) {
    }

    /**
     * 处理渠道回调
     */
    public function handle(Request $request, string $channel, string $id) : JsonResponse
    {
        try {
            // 1. 获取渠道适配器
            // TODO: 从数据库获取渠道配置
            // 读取渠道配置
            $channelModel  = $this->channelRepository->find($id);
            $channelConfig = $channelModel->config; // 实际应从数据库读取

            $adapter = $this->adapterFactory->get($channel, $channelConfig);

            // 2. 验证签名
            if (!$adapter->verifySignature($request)) {
                Log::warning('Signature verification failed', ['channel' => $channel]);
                return response()->json(['error' => 'invalid signature'], 403);
            }

            // 4. 解析并转换消息
            $message = $adapter->parseMessage($request);

            // 设置渠道和应用信息
            $message->appId     = $channelModel->app_id;
            $message->channelId = (string) $channelModel->id;
            // 如果没有渠道会话ID 把渠道会话ID
            $message->channelConversationId = $message->channelConversationId ?? null;
            // 5. 获取或创建会话
            $conversation = $this->conversationService->getOrCreate($message);
            // 获取 系统会话ID
            $message->conversationId = $conversation['conversation_id'];

            // 6. 保存消息记录
            try {
                $this->messageService->save($message);
            } catch (Exception $e) {
                Log::error('Save message failed', ['error' => $e->getMessage()]);
                // 继续处理，不返回错误
            }

            // 7. 存储会话待处理消息：将消息推送到以会话ID为key的Redis ZSET中
            $this->messageService->savePendingMessage($message->conversationId, $message);


            // 8. 第二步：推送事件消息到队列，包含会话ID
            $this->conversationService->triggerEvent(new ConversationEvent($message->conversationId));
            // 9. 快速响应渠道
            $response = $adapter->getSuccessResponse();

            Log::info('Callback processed successfully', [
                'channel'         => $channel,
                'channel_id'      => $id,
                'conversation_id' => $conversation['conversation_id'],
                'message_id'      => $message->messageId,
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
}
