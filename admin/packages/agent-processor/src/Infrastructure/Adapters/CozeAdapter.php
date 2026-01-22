<?php

namespace HuiZhiDa\AgentProcessor\Infrastructure\Adapters;

use Exception;
use HuiZhiDa\AgentProcessor\Domain\Contracts\AgentAdapterInterface;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatRequest;
use HuiZhiDa\AgentProcessor\Domain\Data\ChatResponse;
use HuiZhiDa\AgentProcessor\Domain\Data\Message;
use HuiZhiDa\AgentProcessor\Infrastructure\Utils\StreamResponseParser;
use GuzzleHttp\Client;
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

    public function chat(ChatRequest $request) : ChatResponse
    {
        $botId = $this->config['bot_id'] ?? '';
        if (empty($botId)) {
            throw new InvalidArgumentException('Coze bot_id is required');
        }
        // 获取会话 如果没有会话先创建会话
        // 优先使用 agent_conversation_id，如果没有则使用 conversation_id
        $agentConversationId = $request->agentConversationId;
        if (!$agentConversationId) {
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

        // 构建消息内容
        $content  = '';
        $messages = [];
        foreach ($request->messages as $msg) {
            $messages[] = [
                'role'         => 'user',
                'content'      => $msg->getText(),
                'content_type' => 'text',
            ];
        }


        try {

            // 发起对话
            $response = $this->client->post('v3/chat?conversation_id='.$agentConversationId, [
                'json' => [
                    'bot_id'              => $botId,
                    'user_id'             => 'user_id',// TODO 需要替换
                    'additional_messages' => $messages,
                    'stream'              => false,
                ],
                // 'stream' => true, // 启用流式响应
            ]);

            // 流式读取响应
            $chatResponseData = json_decode($response->getBody()->getContents(), true);
            // 获取对话ID
            $chatId = $chatResponseData['data']['id'];

            $queryIndex = 0;
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
                    $chatResponse       = $chatRetrieveResponseData['data'];
                    $chatRetrieveStatus = $chatRetrieveResponseData['data']['status'];
                    break;
                }
                if ($queryIndex > 100) {
                    throw new RuntimeException("Coze API error: Query timeout");
                }
            }

            // 如果回复完成 ,查询结果
            if ($chatRetrieveStatus === 'completed') {
                $chatResultResponse     = $this->client->post('v3/chat/message/list', [
                    'query' => [
                        'conversation_id' => $agentConversationId,
                        'chat_id'         => $chatId,
                    ],
                ]);
                $chatResultResponseData = json_decode($chatResultResponse->getBody()->getContents(), true);


                $messages = $chatResultResponseData['data'] ?? [];
                $message  = collect($messages)->filter(function ($message) {
                    return $message['role'] === 'assistant'
                           && $message['type'] === 'answer';
                })
                                              ->values()->first();

            }


            $reply                  = $message['content'] ?? '';
            $returnedConversationId = $message['conversation_id'] ?? $agentConversationId;

            $shouldTransfer = false;

            return ChatResponse::from([
                'reply'               => $reply,
                'replyType'           => 'text',
                'shouldTransfer'      => $shouldTransfer,
                'transferReason'      => $shouldTransfer ? 'low_confidence' : null,
                'confidence'          => 0.9,
                'agentConversationId' => $returnedConversationId,
                'metadata'            => [
                    'provider' => 'coze',
                    'bot_id'   => $botId,
                ],
            ]);
        } catch (Exception $e) {
            throw $e;
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

    protected function shouldTransfer(string $reply, ChatRequest $request) : bool
    {
        if (empty($reply) || mb_strlen($reply) < 5) {
            return true;
        }
        return false;
    }
}
