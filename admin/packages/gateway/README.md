# HuiZhiDa Gateway Package

消息网关包，用于处理来自不同渠道（如企业微信）的回调消息，并统一管理消息发送、会话管理和转接功能。

## 功能特性

- ✅ 多渠道适配器支持（企业微信等）
- ✅ 回调消息处理
- ✅ 消息队列集成（Redis）
- ✅ 会话管理
- ✅ 消息存储
- ✅ 消息发送消费者
- ✅ 转接执行器

## 安装

1. 在 `composer.json` 中添加包：

```json
{
    "require": {
        "huizhida/gateway": "^1.0"
    },
    "repositories": [
        {
            "type": "path",
            "url": "./packages/*"
        }
    ]
}
```

2. 运行 `composer install`

3. 发布配置和迁移：

```bash
php artisan vendor:publish --tag=gateway-config
php artisan vendor:publish --tag=gateway-migrations
php artisan migrate
```

## 配置

在 `.env` 文件中配置：

```env
GATEWAY_MODE=debug
GATEWAY_QUEUE_TYPE=redis
GATEWAY_QUEUE_CONNECTION=redis
GATEWAY_INCOMING_QUEUE=incoming_messages
GATEWAY_OUTGOING_QUEUE=outgoing_messages
GATEWAY_TRANSFER_QUEUE=transfer_requests
```

## 使用

### 路由

网关自动注册以下路由：

- `GET /api/gateway/health` - 健康检查
- `POST /api/gateway/callback/{channel}/{appId}` - 渠道回调接口

### 启动消费者

启动消息发送消费者：

```bash
php artisan gateway:message-sender
```

启动转接执行器：

```bash
php artisan gateway:transfer-executor
```

### 适配器

网关使用适配器模式支持不同渠道。当前支持：

- `wecom` - 企业微信

#### 添加新适配器

1. 创建适配器类实现 `ChannelAdapterInterface`
2. 在 `AdapterFactory` 中注册：

```php
$adapterFactory->register('new_channel', NewChannelAdapter::class);
```

## 数据库表

### conversations 表

存储会话信息，包括：
- 会话ID、应用ID、渠道
- 用户信息
- 会话状态
- 转接信息

### messages 表

存储消息记录，包括：
- 消息ID、会话ID
- 消息类型和内容
- 发送方向（接收/发送）
- 消息状态

## 消息格式

网关使用统一的 `Message` 类来处理所有类型的消息，通过 `direction` 字段区分：

- `incoming` - 接收的消息（来自渠道的回调）
- `outgoing` - 发送的消息（发送到渠道）
- `transfer` - 转接请求

### 创建消息示例

```php
use HuiZhiDa\Gateway\Domain\Models\Message;

// 创建接收消息
$incoming = Message::createIncoming();
$incoming->messageId = 'msg_123';
$incoming->appId = 'app_001';
$incoming->channel = 'wecom';
$incoming->messageType = 'text';
$incoming->content->text = 'Hello';

// 创建发送消息
$outgoing = Message::createOutgoing();
$outgoing->messageId = 'msg_456';
$outgoing->conversationId = 'conversation_123';
$outgoing->channel = 'wecom';
$outgoing->reply = 'Reply text';
$outgoing->replyType = 'text';

// 创建转接请求
$transfer = Message::createTransfer();
$transfer->conversationId = 'conversation_123';
$transfer->channel = 'wecom';
$transfer->reason = 'User requested';
$transfer->source = 'rule';
$transfer->priority = 'normal';
```

## 架构说明

```
Domain/
  ├── Contracts/          # 接口定义
  │   ├── ChannelAdapterInterface
  │   └── MessageQueueInterface
  └── Models/             # 领域模型
      ├── Message          # 统一消息格式（支持 incoming/outgoing/transfer）
      ├── UserInfo
      └── MessageContent

Application/
  └── Services/          # 应用服务（已迁移到 core 包）

Infrastructure/
  ├── Adapters/          # 渠道适配器实现
  │   ├── AdapterFactory
  │   └── WecomAdapter
  └── Queue/            # 队列实现
      └── RedisQueue

Http/
  └── Controllers/      # HTTP 控制器
      └── CallbackController

Console/
  └── Commands/        # 控制台命令
      ├── MessageSenderCommand
      └── TransferExecutorCommand
```

## 开发

### 运行测试

```bash
php artisan test
```

### 代码风格

```bash
./vendor/bin/pint
```
