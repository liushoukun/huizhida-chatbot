# Redis 消息队列实现说明

## 概述

本实现使用 **Redis Streams** 作为消息队列，提供发布/订阅、消息确认、消息重试等功能。

## Redis Streams 特性

- ✅ 持久化消息存储
- ✅ 消费者组（Consumer Group）支持
- ✅ 消息确认机制（ACK）
- ✅ 自动消息重投递（PENDING 状态）
- ✅ 阻塞式读取
- ✅ 高性能

## 实现细节

### 1. 发布消息 (Publish)

使用 `XADD` 命令将消息添加到 Stream：

```go
XADD queue_name * data "message_data"
```

- Stream 名称：队列名称（如 `incoming_messages`）
- 消息ID：自动生成（`*`）
- 消息内容：JSON 序列化的消息数据

### 2. 订阅消息 (Subscribe)

使用消费者组模式订阅消息：

```go
XGROUP CREATE queue_name queue_name-group 0 MKSTREAM
XREADGROUP GROUP queue_name-group consumer-name STREAMS queue_name >
```

**消费者组优势**：
- 多个消费者可以并行处理消息
- 消息不会被重复消费（每个消息只被一个消费者处理）
- 支持消息确认和重投递

**消费者名称**：
- 自动生成：`consumer-{timestamp}`
- 每个消费者实例有唯一名称

### 3. 消息确认 (Ack)

使用 `XACK` 命令确认消息已处理：

```go
XACK queue_name consumer-group message-id
```

确认后的消息会从 PENDING 列表中移除。

### 4. 消息拒绝 (Nack)

Redis Streams 不直接支持 Nack，实现方式：

**方案1（当前实现）**：重新发布消息
- 解析消息数据
- 使用 `XADD` 重新发布到队列
- 原消息保持未确认状态

**方案2**：不确认消息
- 消息保持在 PENDING 状态
- 超过一定时间后自动重新投递（需要配置）

### 5. 消息重投递

如果消息未被确认，会保持在 PENDING 状态。可以通过以下方式查看：

```redis
XPENDING queue_name consumer-group
```

查看 PENDING 消息详情：

```redis
XPENDING queue_name consumer-group - + 10
```

重新投递 PENDING 消息：

```redis
XCLAIM queue_name consumer-group new-consumer 0 message-id
```

## 配置示例

```yaml
queue:
  type: "redis"
  config:
    addr: "localhost:6379"
    password: ""
    db: 0
```

## 队列名称

系统使用以下队列：

- `incoming_messages`: 待处理消息队列（网关 → 处理器）
- `outgoing_messages`: 待发送消息队列（处理器 → 网关）
- `transfer_requests`: 转人工请求队列（处理器 → 网关）

每个队列会自动创建对应的消费者组：
- `incoming_messages-group`
- `outgoing_messages-group`
- `transfer_requests-group`

## 性能优化

### 1. 批量读取

当前实现每次读取最多 10 条消息：

```go
Count: 10
```

可以根据实际情况调整。

### 2. 阻塞时间

设置阻塞读取时间为 1 秒：

```go
Block: time.Second
```

避免频繁轮询，同时保证及时响应。

### 3. 缓冲区大小

消息通道缓冲区大小：

```go
msgChan := make(chan Message, 100)
```

可以根据消息处理速度调整。

## 监控和调试

### 查看队列长度

```redis
XLEN incoming_messages
```

### 查看消费者组信息

```redis
XINFO GROUPS incoming_messages
```

### 查看消费者信息

```redis
XINFO CONSUMERS incoming_messages incoming_messages-group
```

### 查看 PENDING 消息

```redis
XPENDING incoming_messages incoming_messages-group
```

### 查看 Stream 信息

```redis
XINFO STREAM incoming_messages
```

## 故障处理

### 1. 消息丢失

Redis Streams 是持久化的，消息不会丢失（除非手动删除）。

### 2. 消息重复

消费者组确保每个消息只被一个消费者处理，不会重复。

### 3. 消费者崩溃

如果消费者崩溃，未确认的消息会保持在 PENDING 状态，可以通过 `XCLAIM` 重新分配给其他消费者。

### 4. 连接断开

实现中使用了 context 来处理连接断开，优雅关闭时会停止消费。

## 注意事项

1. **Redis 版本要求**：Redis 5.0+ 支持 Streams
2. **内存管理**：Streams 会占用内存，建议设置最大长度或使用 TTL
3. **消费者组**：每个队列只需要一个消费者组，多个消费者可以共享同一个组
4. **消息ID**：使用自动生成的 ID（`*`），Redis 会生成时间戳+序列号的 ID

## 扩展功能

### 1. 设置 Stream 最大长度

```redis
XADD queue_name MAXLEN ~ 10000 * data "message"
```

使用 `~` 表示近似长度，性能更好。

### 2. 消息过期

Redis Streams 不支持 TTL，但可以通过定期清理旧消息：

```redis
XTRIM queue_name MAXLEN ~ 10000
```

### 3. 消息范围查询

```redis
XRANGE queue_name - + COUNT 10
```

### 4. 反向查询

```redis
XREVRANGE queue_name + - COUNT 10
```
