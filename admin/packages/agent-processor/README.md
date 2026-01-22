# Agent Processor Package

汇智答智能体处理器包，负责消费会话事件队列，调用智能体处理消息，并推送回复到发送队列。

## 功能特性

1. **消费会话事件队列**：从 `conversation_events` 队列中消费事件
2. **读取未处理消息**：从 Redis ZSET 中获取会话的所有未处理消息
3. **预校验**：在调用智能体前进行规则预判断（关键词匹配、VIP策略等）
4. **调用智能体**：支持多种智能体类型（本地、远程、组合）
5. **格式化响应**：将智能体响应格式化为统一格式
6. **推送回复消息**：将回复推送到 `outgoing_messages` 队列

## 安装

在 `composer.json` 中添加：

```json
{
    "require": {
        "huizhida/agent-processor": "*"
    }
}
```

然后运行：

```bash
composer update
```

## 配置

发布配置文件：

```bash
php artisan vendor:publish --tag=agent-processor-config
```

配置文件位置：`config/agent-processor.php`

### 主要配置项

- `queue.conversation_events_queue`: 会话事件队列名称（默认：`conversation_events`）
- `queue.outgoing_messages_queue`: 待发送消息队列名称（默认：`outgoing_messages`）
- `queue.transfer_requests_queue`: 转人工请求队列名称（默认：`transfer_requests`）
- `pre_check.transfer_keywords`: 转人工关键词（默认：`转人工,人工客服,找人工,真人客服,投诉`）
- `pre_check.vip_direct_transfer`: VIP用户是否直接转人工（默认：`false`）
- `agent.timeout`: 智能体调用超时时间（秒，默认：`30`）

## 使用方法

### 启动队列消费者

```bash
php artisan agent-processor:consume
```

可选参数：
- `--queue`: 指定队列名称（默认使用配置中的队列名）
- `--timeout`: 阻塞超时时间（秒，默认：5）
- `--max-jobs`: 最大处理任务数，0表示无限制（默认：0）

示例：

```bash
# 消费默认队列
php artisan agent-processor:consume

# 消费指定队列，设置超时10秒
php artisan agent-processor:consume --queue=conversation_events --timeout=10

# 处理100个任务后退出
php artisan agent-processor:consume --max-jobs=100
```

## 架构说明

### 处理流程

1. **消费会话事件**：从 `conversation_events` 队列消费事件，获取会话ID
2. **读取未处理消息**：从 Redis ZSET `conversation:messages:{conversation_id}` 中获取所有未处理消息
3. **预校验**：执行规则预判断
   - 检查会话是否已转人工
   - 关键词匹配
   - VIP策略检查
4. **调用智能体**：
   - 获取应用绑定的智能体
   - 创建智能体适配器
   - 调用智能体处理消息
5. **处理响应**：
   - 如果智能体建议转人工，推入 `transfer_requests` 队列
   - 否则，格式化响应并推入 `outgoing_messages` 队列
6. **清理**：从 Redis ZSET 中移除已处理的消息

### 支持的智能体类型

- **本地智能体**：Ollama
- **远程智能体**：OpenAI、通义千问、Coze、腾讯元启、自定义HTTP
- **组合智能体**：组合本地和远程智能体

### 预校验规则

预校验在调用智能体前执行，可以快速处理明确的转人工请求：

- **已转人工**：会话状态为 `transferred` 或 `pending_human`，跳过处理
- **关键词匹配**：消息中包含转人工关键词，直接转人工
- **VIP策略**：如果配置了VIP用户直接转人工，则转人工
- **正常处理**：其他情况调用智能体

## 开发

### 目录结构

```
agent-processor/
├── config/
│   └── agent-processor.php          # 配置文件
├── src/
│   ├── AgentProcessorServiceProvider.php
│   ├── Application/
│   │   └── Services/
│   │       ├── AgentService.php          # 智能体服务
│   │       ├── MessageProcessorService.php # 消息处理器服务
│   │       └── PreCheckService.php        # 预校验服务
│   ├── Console/
│   │   └── Commands/
│   │       └── ProcessConversationEventsCommand.php # 队列消费命令
│   ├── Domain/
│   │   └── Contracts/
│   │       └── AgentAdapterInterface.php  # 智能体适配器接口
│   └── Infrastructure/
│       └── Adapters/
│           ├── AgentAdapterFactory.php    # 适配器工厂
│           ├── OllamaAdapter.php          # Ollama适配器
│           ├── OpenAIAdapter.php          # OpenAI适配器
│           ├── QwenAdapter.php             # 通义千问适配器
│           ├── CozeAdapter.php             # Coze适配器
│           ├── TencentYuanqiAdapter.php   # 腾讯元启适配器
│           ├── HttpAdapter.php             # HTTP适配器
│           └── HybridAdapter.php           # 组合适配器
└── README.md
```

### 扩展智能体适配器

要实现新的智能体适配器：

1. 实现 `AgentAdapterInterface` 接口
2. 在 `AgentAdapterFactory` 中注册

示例：

```php
class CustomAdapter implements AgentAdapterInterface
{
    public function initialize(array $config): void
    {
        // 初始化逻辑
    }

    public function chat(\HuiZhiDa\AgentProcessor\Domain\Data\ChatRequest $request): \HuiZhiDa\AgentProcessor\Domain\Data\ChatResponse
    {
        // 调用智能体API
        // 返回格式化的响应
        return \HuiZhiDa\AgentProcessor\Domain\Data\ChatResponse::from([
            'reply' => '回复内容',
            'replyType' => 'text',
            'shouldTransfer' => false,
            'confidence' => 1.0,
        ]);
    }

    public function healthCheck(): bool
    {
        // 健康检查逻辑
        return true;
    }
}
```

## 依赖

- `huizhida/gateway`: 消息队列接口、会话服务
- `huizhida/agent`: 智能体模型和仓库
- `guzzlehttp/guzzle`: HTTP客户端

## License

MIT
