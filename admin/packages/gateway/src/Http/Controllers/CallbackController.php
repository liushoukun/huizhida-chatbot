<?php

namespace HuiZhiDa\Gateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use HuiZhiDa\Gateway\Infrastructure\Adapters\AdapterFactory;
use HuiZhiDa\Gateway\Application\Services\ConversationService;
use HuiZhiDa\Gateway\Application\Services\MessageService;
use HuiZhiDa\Gateway\Domain\Contracts\MessageQueueInterface;

class CallbackController
{
    public function __construct(
        protected AdapterFactory $adapterFactory,
        protected ConversationService $conversationService,
        protected MessageService $messageService,
        protected MessageQueueInterface $mq
    ) {
    }

    /**
     * 处理渠道回调
     */
    public function handle(Request $request, string $channel, string $appId): JsonResponse
    {
        try {
            // 1. 获取渠道适配器
            // TODO: 从数据库获取渠道配置
            $channelConfig = []; // 实际应从数据库读取
            $adapter = $this->adapterFactory->get($channel, $channelConfig);

            // 2. 验证签名
            if (!$adapter->verifySignature($request)) {
                Log::warning('Signature verification failed', ['channel' => $channel]);
                return response()->json(['error' => 'invalid signature'], 403);
            }

            // 3. 读取请求体
            $rawData = $request->getContent();

            // 4. 解析并转换消息
            $message = $adapter->parseMessage($rawData);
            $message->appId = $appId;
            $message->channel = $channel;

            // 5. 获取或创建会话
            $conversation = $this->conversationService->getOrCreate($message);
            $message->conversationId = $conversation['conversation_id'];

            // 6. 保存消息记录
            try {
                $this->messageService->save($message);
            } catch (\Exception $e) {
                Log::error('Save message failed', ['error' => $e->getMessage()]);
                // 继续处理，不返回错误
            }

            // 7. 推入待处理队列
            $queueName = config('gateway.queue.incoming_queue', 'incoming_messages');
            $this->mq->publish($queueName, $message->toArray());

            // 8. 快速响应渠道
            $response = $adapter->getSuccessResponse();

            Log::info('Callback processed successfully', [
                'channel' => $channel,
                'app_id' => $appId,
                'conversation_id' => $conversation['conversation_id'],
                'message_id' => $message->messageId,
            ]);

            return response()->json($response, 200);
        } catch (\InvalidArgumentException $e) {
            Log::error('Unsupported channel', [
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'unsupported channel'], 400);
        } catch (\Exception $e) {
            Log::error('Callback processing failed', [
                'channel' => $channel,
                'app_id' => $appId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'internal server error'], 500);
        }
    }
}
