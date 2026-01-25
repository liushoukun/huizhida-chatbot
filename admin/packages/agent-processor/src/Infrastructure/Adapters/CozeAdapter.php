<?php

namespace HuiZhiDa\AgentProcessor\Infrastructure\Adapters;

use Exception;
use GuzzleHttp\Client;
use HuiZhiDa\AgentProcessor\Domain\Contracts\AgentAdapterInterface;
use HuiZhiDa\AgentProcessor\Domain\Data\AgentChatRequest;
use HuiZhiDa\AgentProcessor\Domain\Data\AgentChatResponse;
use HuiZhiDa\Core\Domain\Conversation\DTO\AgentMessage;
use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Coze 远程智能体适配器
 */
class CozeAdapter implements AgentAdapterInterface
{
    protected Client $client;
    protected array  $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->initialize($config);
    }

    public function initialize(array $config) : void
    {
        $botId   = $config['bot_id'] ?? '';
        $token   = $config['token'] ?? '';
        $apiBase = $config['api_base'] ?? 'https://api.coze.cn';

        if (empty($token) || empty($botId)) {
            throw new InvalidArgumentException('Coze API key is required');
        }

        $this->client = new Client([
            'base_uri' => rtrim($apiBase, '/'),
            'timeout'  => $config['timeout'] ?? 30,
            'headers'  => [
                'Authorization' => 'Bearer '.$token,
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    public function chat(AgentChatRequest $request) : AgentChatResponse
    {
        $botId = $this->config['bot_id'] ?? '';
        if (empty($botId)) {
            throw new InvalidArgumentException('Coze bot_id is required');
        }

        $chatResponse = new AgentChatResponse();
        // 1、创建会话
        // 获取会话 如果没有会话先创建会话
        // 优先使用 agent_conversation_id，如果没有则使用 conversation_id

        $agentConversationId = $request->agentConversationId;
        if (!$agentConversationId) {
            Log::debug('Coze 创建会话',
                ['agentConversationId' => $request->agentConversationId, 'conversationId' => $request->conversationId]);

            $response = $this->client->post('v1/conversation/create', [
                'json'        => [
                    'bot_id' => $botId,
                ],
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new RuntimeException("Coze API error: ".$response->getStatusCode());
            }
            $data                = json_decode($response->getBody()->getContents(), true);
            $agentConversationId = $data['data']['id'] ?? null;
            if (!$agentConversationId) {
                throw new RuntimeException("Coze API error: Conversation ID not found");
            }

        }

        $chatResponse->agentConversationId = $agentConversationId;


        // 2、发起对话
        Log::debug('Coze 发起对话', ['agentConversationId' => $agentConversationId, 'conversationId' => $request->conversationId]);
        $messages = [];
        foreach ($request->messages as $msg) {
            // TODO ,需要根据格式转换
            if ($msg->contentType === ContentType::Text) {

                $messages[] = [
                    'role'         => 'user',
                    'content'      => $msg->getContent()->text ?? '',
                    'content_type' => 'text',
                ];
            } else {
                // 组合消息
            }
        }


        try {

            // 发起对话
            $response = $this->client->post('v3/chat?conversation_id='.$agentConversationId, [
                'json' => [
                    'bot_id'              => $botId,
                    'user_id'             => $request->user->getID(),// TODO 需要替换
                    'additional_messages' => $messages,
                    'stream'              => false,
                ],
            ]);

            // 流式读取响应
            $chatResponseData = json_decode($response->getBody()->getContents(), true);
            // 获取对话ID
            $chatId = $chatResponseData['data']['id'];

            $queryIndex = 0;


            $agentMessages = [];

            while (true) {
                sleep(2);
                $queryIndex++;
                // 查询对话状态
                $chatRetrieveResponse     = $this->client->post('v3/chat/retrieve', [
                    'query' => [
                        'conversation_id' => $agentConversationId,
                        'chat_id'         => $chatId,
                    ],
                    'json'  => [
                        'bot_id'              => $botId,
                        'user_id'             => 'user_id',// TODO 需要替换
                        'additional_messages' => $messages,
                        'stream'              => false,
                    ],
                    // 'stream' => true, // 启用流式响应
                ]);
                $chatRetrieveResponseData = json_decode($chatRetrieveResponse->getBody()->getContents(), true);

                // 如果 最终状态
                $finalStatusList = [
                    'completed',
                    'failed',
                    'requires_action',
                    'canceled'
                ];

                if (in_array($chatRetrieveResponseData['data']['status'], $finalStatusList)) {
                    $chatRetrieveStatus = $chatRetrieveResponseData['data']['status'];
                    break;
                }
                if ($queryIndex > 100) {
                    throw new RuntimeException("Coze API error: Query timeout");
                }
            }
            // 3.  获取回复结果
            if ($chatRetrieveStatus === 'completed') {
                Log::debug('Coze 获取回复结果',
                    ['agentConversationId' => $agentConversationId, 'conversationId' => $request->conversationId]);
                $chatResultResponse     = $this->client->post('v3/chat/message/list', [
                    'query' => [
                        'conversation_id' => $agentConversationId,
                        'chat_id'         => $chatId,
                    ],
                ]);
                $chatResultResponseData = json_decode($chatResultResponse->getBody()->getContents(), true);

                Log::debug('Coze 获取回复结果', ['chatResultResponseData' => $chatResultResponseData]);
                $responseMessages = collect($chatResultResponseData['data'] ?? [])->filter(function ($message) {
                    return $message['role'] === 'assistant'
                           && $message['type'] === 'answer';
                })->values();

                foreach ($responseMessages as $responseMessage) {
                    // TODO 解析Message 根据类型解析
                    // 如果有媒体对象 需要解析媒体对象
                    $agentMessage              = new AgentMessage();
                    $agentMessage->messageType = MessageType::Message;
                    $agentMessage->setContentData(ContentType::Text, [
                        'text' => $responseMessage['content'] ?? '',
                    ]);

                    $agentMessages[] = $agentMessage;
                }

            }

            // 回复结果不要 markdown 格式 TODO

            Log::debug('Coze 返回结果', [
                'agentConversationId' => $agentConversationId,
                'conversationId'      => $request->conversationId,
                'agentMessages'       => $agentMessages,
            ]);


            $chatResponse->messages            = $agentMessages;
            $chatResponse->conversationId      = $request->conversationId;
            $chatResponse->agentConversationId = $agentConversationId;

            return $chatResponse;
        } catch (Exception $e) {

            Log::error('Coze API error', [
                'error'  => $e->getMessage(),
                'config' => $this->config,
            ]);
            throw new RuntimeException("Coze API error: ".$e->getMessage());
        }
    }

    public function healthCheck() : bool
    {
        // Coze 没有专门的健康检查接口
        return true;
    }

    protected function shouldTransfer(string $reply, AgentChatRequest $request) : bool
    {
        if (empty($reply) || mb_strlen($reply) < 5) {
            return true;
        }
        return false;
    }
}
