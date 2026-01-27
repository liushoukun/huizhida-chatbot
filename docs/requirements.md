# 汇智答-智能客服平台

## 1. 文档信息

| 项目 | 内容 |
|------|------|
| 项目名称 | 汇智答 (HuiZhiDa) |
| 英文名称 | HuiZhiDa ChatBot |
| 项目代号 | HZD |
| 文档版本 | v2.3.0 |
| 创建日期 | 2026-01-20 |
| 更新日期 | 2026-01-27 |
| 文档状态 | 架构优化 |
| Slogan | 汇聚智能，有问必答 |

---

## 2. 项目概述

### 2.1 项目背景

随着企业多渠道运营的普及，客户服务需要同时对接企业微信、淘宝、抖音等多个平台。各平台消息格式、API接口各不相同，同时企业希望利用AI智能体来提升客服效率。

**汇智答** 旨在构建一个统一的智能客服中枢平台，实现多平台消息的统一接入、智能处理和自动回复。

> **汇智答** = **汇**聚 + **智**能 + 应**答**
>
> 汇聚智能，有问必答

### 2.2 项目目标

1. **统一接入**：对接多个主流客服渠道，将不同格式的消息转换为统一格式
2. **智能处理**：集成多种AI智能体平台，实现智能自动回复
3. **灵活扩展**：采用插件化架构，方便扩展新的平台和智能体
4. **人机协作**：支持智能体与人工客服无缝切换
5. **可视化管理**：提供管理后台进行配置和监控

### 2.3 术语定义

| 术语 | 定义 |
|------|------|
| 渠道(Channel) | 客服消息来源渠道，如企业微信、淘宝等 |
| 智能体(Agent) | AI对话处理引擎，实现统一接口，支持本地、远程、组合等多种模式 |
| 智能体适配器(Agent Adapter) | 智能体的统一抽象接口，所有智能体实现都遵循此接口 |
| 本地智能体(Local Agent) | 基于本地模型的智能体实现，如Ollama、llama.cpp |
| 远程智能体(Remote Agent) | 基于远程API的智能体实现，如OpenAI、通义千问、Coze |
| 组合智能体(Hybrid Agent) | 组合本地+远程的智能体实现，本地分类后路由到不同处理器 |
| 消息处理器(Processor) | 核心处理组件，负责消息路由和智能体调用 |
| 会话(Conversation) | 用户与客服之间的一次完整对话过程 |
| 应用(Application) | 一个独立的客服服务实例，可配置多个渠道，每个渠道可绑定不同的智能体 |
| 回调(Callback) | 渠道主动推送消息到本系统的接口 |

---

## 3. 系统架构

### 3.1 整体架构图

系统采用 **双队列驱动的微服务架构**，通过 `inputs` 和 `outputs` 两个消息队列实现服务解耦，按会话维度聚合消息处理：

```mermaid
flowchart TB
    subgraph Clients["客户端渠道"]
        WeChat["企业微信"]
        Taobao["淘宝"]
        Douyin["抖音"]
        Others["其他渠道"]
    end

    subgraph GatewayService["消息网关服务 (Gateway)"]
        subgraph GatewayIn["接收层"]
            Adapters["渠道适配器"]
            Transformer["消息格式转换"]
            ConversationWriter["会话记录"]
        end
        
        subgraph GatewayOut["发送层"]
            OutputConsumer["Outputs消费者"]
            MsgSender["消息发送器"]
            StateHandler["状态处理器"]
        end
    end

    subgraph MessageLayer["消息层"]
        subgraph MessageStorage["消息存储 (Redis)"]
            InputMessages["input-messages:{conv_id}<br/>ZSET 待处理消息"]
        end
        
        subgraph MessageQueue["消息队列 (MQ)"]
            InputsQueue["inputs<br/>输入事件队列"]
            OutputsQueue["outputs<br/>输出事件队列"]
        end
    end

    subgraph ProcessorService["核心处理器服务 (Processor)"]
        InputConsumer["Inputs消费者"]
        ConvLock["会话锁<br/>lock:{conv_id}"]
        
        subgraph CoreProcess["核心处理流程"]
            FetchMessages["获取会话消息列表"]
            
            subgraph EventHandler["1️⃣ 事件处理层"]
                HandleClose["外部结束对话"]
                HandleStateChange["外部状态变更"]
            end
            
            subgraph PreCheck["2️⃣ 预校验层"]
                CheckTransferred["会话已转人工?"]
                CheckKeyword["内容要求转人工?"]
            end
            
            subgraph AgentLayer["3️⃣ 智能体处理层"]
                AgentInterface["IAgentAdapter"]
                LocalAgent["本地智能体"]
                RemoteAgent["远程智能体"]
            end
            
            subgraph PostProcess["4️⃣ 回复预处理层"]
                CheckForceTransfer["强制转人工判断"]
                PackageOutput["封装Output消息"]
            end
        end
    end

    subgraph AdminService["管理后台服务 (PHP Laravel)"]
        Filament["Filament Admin"]
        AppMgr["应用管理"]
        ConfigMgr["配置管理"]
    end

    subgraph Storage["数据存储层"]
        MySQL[("MySQL<br/>主数据库")]
        Redis[("Redis<br/>缓存/锁/消息")]
        VectorDB[("向量数据库")]
    end

    subgraph AI["智能体服务"]
        Ollama["本地模型<br/>Ollama"]
        RemoteAI["远程平台<br/>OpenAI/Coze"]
    end

    %% 入站流程
    Clients --> Adapters
    Adapters --> Transformer --> ConversationWriter
    ConversationWriter -->|"存储消息"| InputMessages
    ConversationWriter -->|"发布事件"| InputsQueue
    ConversationWriter --> MySQL

    %% 核心处理流程
    InputsQueue -->|"消费事件"| InputConsumer
    InputConsumer --> ConvLock --> FetchMessages
    FetchMessages -->|"获取消息"| InputMessages
    FetchMessages --> EventHandler --> PreCheck --> AgentLayer --> PostProcess
    AgentLayer --> AI
    PostProcess -->|"发布事件"| OutputsQueue

    %% 出站流程
    OutputsQueue -->|"消费事件"| OutputConsumer
    OutputConsumer --> MsgSender --> Clients
    OutputConsumer --> StateHandler --> MySQL

    %% 数据存储
    GatewayService --> MySQL
    GatewayService --> Redis
    ProcessorService --> MySQL
    ProcessorService --> Redis
    AdminService --> MySQL

    %% 样式定义
    classDef clientStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef gatewayStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef storageStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000
    classDef queueStyle fill:#FCE4EC,stroke:#C2185B,stroke-width:2px,color:#000
    classDef processorStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef adminStyle fill:#FCE4EC,stroke:#C2185B,stroke-width:2px,color:#000
    classDef dbStyle fill:#E0F2F1,stroke:#00796B,stroke-width:3px,color:#000
    classDef aiStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000

    %% 应用样式
    class WeChat,Taobao,Douyin,Others clientStyle
    class Adapters,Transformer,ConversationWriter,OutputConsumer,MsgSender,StateHandler gatewayStyle
    class InputMessages storageStyle
    class InputsQueue,OutputsQueue queueStyle
    class InputConsumer,ConvLock,FetchMessages,HandleClose,HandleStateChange,CheckTransferred,CheckKeyword,AgentInterface,LocalAgent,RemoteAgent,CheckForceTransfer,PackageOutput processorStyle
    class Filament,AppMgr,ConfigMgr adminStyle
    class MySQL,Redis,VectorDB dbStyle
    class Ollama,RemoteAI aiStyle
```

### 3.2 服务职责划分

| 服务 | 技术栈 | 核心职责 |
|------|--------|----------|
| **消息网关 (Gateway)** | PHP Laravel | 渠道回调接收、签名验证、消息格式转换、会话记录、消息入队 (inputs)、消费输出队列 (outputs)、消息发送、状态同步 |
| **核心处理器 (Processor)** | PHP Laravel | 消费输入队列 (inputs)、事件处理、规则预校验、智能体调用、回复预处理、发布到输出队列 (outputs) |
| **管理后台 (Admin)** | PHP Laravel + Filament | 应用管理、渠道配置、智能体配置、数据统计、系统监控 |

### 3.3 技术栈

| 组件 | 技术选型 | 说明 |
|------|----------|------|
| **消息网关** | PHP Laravel | 渠道适配器、消息转换、队列生产/消费 |
| **核心处理器** | PHP Laravel | 消息处理、智能体调用、业务逻辑 |
| **管理后台** | PHP Laravel + Filament | 快速开发管理界面，功能完善 |
| **消息队列** | Redis Streams / RabbitMQ | 服务间消息传递，可配置选择 |
| **消息存储** | Redis ZSET | 会话级消息聚合存储 |
| **分布式锁** | Redis | 会话级并发控制 |
| **数据库** | MySQL 8.0 | 持久化存储，事务支持 |
| **缓存** | Redis | 会话缓存、配置缓存 |
| **向量数据库** | Chroma / Milvus | FAQ语义检索，知识库 |
| **本地模型** | Ollama | 本地LLM推理 |
| **部署** | Docker + Docker Compose / K8s | 容器化部署 |

### 3.4 服务间通信

系统采用 **双队列驱动** 模式，`inputs` 队列驱动消息处理，`outputs` 队列驱动消息发送：

```mermaid
flowchart LR
    subgraph Gateway["消息网关"]
        G1["接收回调<br/>(生产者)"]
        G2["发送消息<br/>(消费者)"]
    end

    subgraph Storage["Redis 存储"]
        S1["input-messages:{conv_id}<br/>ZSET"]
        S2["lock:{conv_id}<br/>分布式锁"]
    end

    subgraph Queue["消息队列"]
        Q1["inputs<br/>输入事件队列"]
        Q2["outputs<br/>输出事件队列"]
    end

    subgraph Processor["核心处理器"]
        P1["消费inputs<br/>(消费者)"]
        P2["核心处理流程"]
        P3["发布outputs<br/>(生产者)"]
    end

    subgraph Admin["管理后台"]
        A1["配置管理"]
    end

    subgraph DB["数据库"]
        MySQL[("MySQL")]
    end

    G1 -->|"1.存储消息"| S1
    G1 -->|"2.发布事件"| Q1
    Q1 -->|"3.消费事件"| P1
    P1 -->|"4.获取锁"| S2
    P1 -->|"5.获取消息"| S1
    P1 --> P2 --> P3
    P3 -->|"6.发布事件"| Q2
    Q2 -->|"7.消费事件"| G2

    Gateway <-->|读写| DB
    Processor <-->|读写| DB
    Admin <-->|读写| DB

    %% 样式定义
    classDef gatewayStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef storageStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000
    classDef queueStyle fill:#FCE4EC,stroke:#C2185B,stroke-width:2px,color:#000
    classDef processorStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef adminStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef dbStyle fill:#E0F2F1,stroke:#00796B,stroke-width:3px,color:#000

    %% 应用样式
    class G1,G2 gatewayStyle
    class S1,S2 storageStyle
    class Q1,Q2 queueStyle
    class P1,P2,P3 processorStyle
    class A1 adminStyle
    class MySQL dbStyle
```

**数据流时序**：

```mermaid
sequenceDiagram
    participant C as 渠道
    participant G as Gateway
    participant R as Redis
    participant IQ as inputs队列
    participant P as Processor
    participant OQ as outputs队列

    Note over C,OQ: 入站流程
    C->>G: 1. 渠道回调
    G->>G: 2. 验签+转换
    G->>R: 3. ZADD input-messages:{conv_id}
    G->>IQ: 4. PUBLISH inputs事件
    G-->>C: 5. 快速响应

    Note over C,OQ: 处理流程
    IQ->>P: 6. 消费inputs事件
    P->>R: 7. SETNX lock:{conv_id}
    P->>R: 8. ZRANGEBYSCORE input-messages:{conv_id}
    P->>P: 9. 事件处理→预校验→智能体→回复预处理
    P->>R: 10. ZREMRANGEBYSCORE (删除已处理)
    P->>R: 11. DEL lock:{conv_id}
    P->>OQ: 12. PUBLISH outputs事件

    Note over C,OQ: 出站流程
    OQ->>G: 13. 消费outputs事件
    G->>C: 14. 发送消息/状态同步
```

**支持的消息中间件**：

| 中间件 | 适用场景 | 说明 |
|--------|----------|------|
| **Redis Streams** | 小规模/开发测试 | 简单易用，低延迟 |
| **RabbitMQ** | 中等规模/生产环境 | 功能丰富，可靠性高 |

### 3.5 核心数据结构定义

#### 3.5.1 队列定义

| 队列名 | 方向 | 说明 |
|--------|------|------|
| `inputs` | Gateway → Processor | 输入事件通知队列，触发消息处理 |
| `outputs` | Processor → Gateway | 输出事件队列，包含完整的回复/状态变更消息 |

#### 3.5.2 Redis 存储结构

| Key格式 | 数据结构 | 说明 |
|---------|----------|------|
| `input-messages:{conversation_id}` | ZSET | 会话待处理消息集合，score=时间戳，value=消息JSON |
| `lock:conversation:{conversation_id}` | STRING | 会话处理分布式锁，防止并发处理 |

#### 3.5.3 消息 DTO 定义

**会话 DTO (ConversationDTO)**：

| 字段 | 类型 | 说明 |
|------|------|------|
| conversationId | string | 会话唯一ID |
| appId | string | 应用ID |
| channel | string | 渠道类型 (wecom/taobao/douyin) |
| channelId | string | 渠道配置ID |
| user | UserInfo | 用户信息 |
| status | string | 会话状态 (active/transferred/closed) |
| agentId | int? | 绑定智能体ID |
| context | array | 会话上下文 |
| transferReason | string? | 转人工原因 |
| transferSource | string? | 转人工来源 (rule/agent) |
| createdAt | DateTime | 创建时间 |
| updatedAt | DateTime | 更新时间 |

**消息 DTO (MessageDTO)**：

| 字段 | 类型 | 说明 |
|------|------|------|
| messageId | string | 消息唯一ID |
| conversationId | string | 会话ID |
| type | string | 消息类型 (message/event) |
| direction | string | 消息方向 (in/out) |
| messageType | string | 内容类型 (text/image/voice/event) |
| content | array | 消息内容 |
| timestamp | int | 时间戳 |
| metadata | array | 扩展元数据 |

**输入事件消息 (inputs 队列)**：

```json
{
  "type": "conversation_message",
  "conversation_id": "conv_001",
  "app_id": "app_001",
  "channel": "wecom",
  "timestamp": 1705747200000
}
```

**输出事件消息 (outputs 队列)**：

```json
{
  "type": "reply",
  "conversation_id": "conv_001",
  "channel": "wecom",
  "messages": [
    {
      "message_type": "text",
      "content": { "text": "您好，请问有什么可以帮您？" }
    }
  ],
  "timestamp": 1705747201000
}
```

```json
{
  "type": "transfer_human",
  "conversation_id": "conv_001",
  "channel": "wecom",
  "reason": "用户请求转人工",
  "source": "rule",
  "priority": "normal",
  "timestamp": 1705747201000
}
```

```json
{
  "type": "close_conversation",
  "conversation_id": "conv_001",
  "channel": "wecom",
  "reason": "会话超时",
  "timestamp": 1705747201000
}
```

#### 3.5.4 MQ 抽象接口

消息队列接口定义，支持多种实现（Redis Streams、RabbitMQ等）：

| 方法 | 说明 |
|------|------|
| `publish(string $queue, array $message): void` | 发布消息到队列 |
| `consume(string $queue, callable $handler): void` | 消费队列消息 |
| `ack(string $messageId): void` | 确认消息消费 |
| `nack(string $messageId): void` | 拒绝消息，重新入队 |

**实现类**：
- `RedisStreamQueue` - Redis Streams 实现
- `RabbitMQQueue` - RabbitMQ 实现

---

## 4. 功能需求

### 4.1 消息网关模块 (Gateway)

消息网关是系统的入口和出口服务，负责与各客服渠道的直接交互，同时消费 outputs 队列处理出站消息。

#### 4.1.1 核心职责

| 职责 | 说明 |
|------|------|
| **渠道回调接收** | 接收各渠道推送的客服消息 |
| **签名验证** | 验证渠道请求的合法性 |
| **消息格式转换** | 将渠道消息转为统一格式 (MessageDTO) |
| **会话管理** | 创建/更新会话信息 |
| **消息入队** | 将消息存入 Redis ZSET，发布事件到 inputs 队列 |
| **消费 outputs** | 消费输出队列，根据消息类型处理 |
| **消息发送** | 调用渠道API发送回复消息 |
| **状态同步** | 处理会话状态变更（转人工、关闭会话等） |

#### 4.1.2 渠道回调接收

**功能描述**：接收各渠道推送的客服消息回调

**支持渠道**：
- 企业微信客服
- 淘宝/天猫客服
- 抖音客服
- 京东客服
- 拼多多客服
- 自定义Webhook

**接口规范**：

```
POST /api/callback/{channel}/{app_id}
```

**处理流程**：

消息回调处理采用**两步处理机制**，优化连续消息的处理效率：

1. **第一步**：将消息推送到以会话ID为key的Redis ZSET中 (`input-messages:{conversation_id}`)
2. **第二步**：推送事件消息到 `inputs` 队列，触发核心处理器批量处理该会话的所有未处理消息

```mermaid
sequenceDiagram
    participant C as 客服渠道
    participant G as Gateway
    participant R as Redis
    participant Q as inputs队列
    participant DB as MySQL

    C->>G: POST /callback/{channel}/{app_id}
    activate G
    G->>G: 1. 验证签名/Token
    G->>G: 2. 解析渠道消息格式
    G->>G: 3. 转换为 MessageDTO
    
    G->>DB: 4. 创建/更新会话
    activate DB
    DB-->>G: 会话信息
    deactivate DB
    
    G->>DB: 5. 保存消息记录
    activate DB
    DB-->>G: 保存成功
    deactivate DB
    
    Note over G,R: 第一步：存储到会话 ZSET
    G->>R: 6. ZADD input-messages:{conv_id}<br/>(消息JSON, 时间戳)
    activate R
    R-->>G: 添加成功
    deactivate R
    
    Note over G,Q: 第二步：发布输入事件
    G->>Q: 7. PUBLISH inputs<br/>{type, conversation_id}
    activate Q
    Q-->>G: 发布成功
    deactivate Q
    
    G-->>C: 8. 200 OK (快速响应)
    deactivate G

    Note over C,Q: 快速响应，异步处理
```

**处理步骤**：

1. 获取渠道适配器并验证签名
2. 解析并转换消息格式为统一 DTO
3. 创建/更新会话记录
4. 保存消息记录到数据库
5. 存储消息到 Redis ZSET (`input-messages:{conversation_id}`)
6. 发布输入事件到 `inputs` 队列
7. 快速响应渠道（200 OK）

#### 4.1.3 消费 outputs 队列

Gateway 负责消费 `outputs` 队列，根据消息类型执行不同的操作：

| 消息类型 | 操作 |
|----------|------|
| `reply` | 调用渠道API发送回复消息 |
| `transfer_human` | 执行转人工流程 |
| `close_conversation` | 执行关闭会话流程 |
| `state_change` | 同步会话状态到渠道 |

```mermaid
sequenceDiagram
    participant Q as outputs队列
    participant G as Gateway
    participant C as 客服渠道
    participant DB as MySQL

    loop 持续消费
        Q->>G: 消费 outputs 事件
        activate G
        
        alt type = reply
            G->>G: 获取渠道适配器
            G->>G: 转换为渠道消息格式
            G->>C: 调用渠道发送API
            C-->>G: 发送结果
            G->>DB: 保存发送消息记录
            
        else type = transfer_human
            G->>G: 获取渠道适配器
            G->>C: 发送转人工提示消息
            G->>C: 调用渠道转人工API
            C-->>G: 转人工结果
            G->>DB: 更新会话状态
            
        else type = close_conversation
            G->>G: 获取渠道适配器
            G->>C: 调用渠道关闭会话API (如有)
            G->>DB: 更新会话状态为closed
        end
        
        G->>Q: ACK 确认消费
        deactivate G
    end
```

**处理逻辑**：

- **reply 类型**：转换为渠道消息格式，调用渠道API发送，保存发送记录
- **transfer_human 类型**：发送转人工提示消息，调用渠道转人工API，更新会话状态
- **close_conversation 类型**：调用渠道关闭会话API（如支持），更新会话状态为 closed

#### 4.1.5 统一消息格式

**消息结构定义**：

| 字段 | 类型 | 说明 |
|------|------|------|
| message_id | string | 消息唯一ID |
| app_id | string | 应用ID |
| channel | string | 渠道类型 |
| channel_message_id | string | 渠道消息ID |
| conversation_id | string | 会话ID |
| user | UserInfo | 用户信息 |
| message_type | string | 消息类型 |
| content | MessageContent | 消息内容 |
| timestamp | int64 | 时间戳 |
| raw_data | json? | 原始数据（可选） |

**UserInfo 结构**：

| 字段 | 类型 | 说明 |
|------|------|------|
| channel_user_id | string | 渠道用户ID |
| nickname | string | 用户昵称 |
| avatar | string | 头像URL |
| is_vip | bool | 是否VIP用户 |
| tags | []string | 用户标签 |

**MessageContent 结构**：

| 字段 | 类型 | 说明 |
|------|------|------|
| text | string? | 文本内容 |
| media_url | string? | 媒体URL |
| media_type | string? | 媒体类型 |
| extra | object? | 扩展信息 |

**JSON格式示例**：

```json
{
  "message_id": "msg_20260120_001",
  "app_id": "app_001",
  "channel": "wecom",
  "channel_message_id": "wx_msg_123456",
  "conversation_id": "conv_001",
  "user": {
    "channel_user_id": "user_wx_001",
    "nickname": "张三",
    "avatar": "https://...",
    "is_vip": false,
    "tags": ["新客户"]
  },
  "message_type": "text",
  "content": {
    "text": "你好，我想咨询一下退款流程"
  },
  "timestamp": 1705747200000
}
```

**消息类型枚举**：
- `text` - 文本消息
- `image` - 图片消息
- `voice` - 语音消息
- `video` - 视频消息
- `file` - 文件消息
- `link` - 链接消息
- `location` - 位置消息
- `event` - 事件消息（进入会话、结束会话等）

---

### 4.2 会话管理模块

#### 4.2.1 会话创建与维护

**功能描述**：管理用户与客服之间的会话状态

**会话状态**：
- `active` - 活跃中
- `pending_agent` - 等待智能体处理
- `pending_human` - 等待人工处理
- `transferred` - 已转人工
- `closed` - 已关闭

```mermaid
stateDiagram-v2
    [*] --> active: 用户发起会话
    
    active --> pending_agent: 等待智能体处理
    pending_agent --> active: 智能体回复
    
    active --> pending_human: 规则触发转人工
    pending_agent --> pending_human: 智能体建议转人工
    
    pending_human --> transferred: 人工客服接入
    transferred --> active: 人工回复
    
    active --> closed: 会话结束
    transferred --> closed: 人工结束会话
    active --> closed: 超时自动关闭
    
    closed --> [*]

    note right of active
        活跃状态
        正常对话中
    end note
    
    note right of pending_human
        等待人工
        已触发转人工
    end note
    
    note right of transferred
        已转人工
        人工客服处理中
    end note
```

**会话数据结构**：

```json
{
  "conversation_id": "string",
  "app_id": "string",
  "channel": "string",
  "user": {
    "channel_user_id": "string",
    "nickname": "string",
    "avatar": "string",
    "is_vip": false,                // 是否VIP用户
    "tags": []                      // 用户标签
  },
  "status": "string",               // active|pending_agent|pending_human|transferred|closed
  "current_agent_id": "number",     // 当前使用的智能体ID
  "context": {
    "history": [],                  // 对话历史
    "variables": {},                // 会话变量
    "intent": "string"              // 识别的意图
  },
  "transfer_info": {                // 转人工信息（如有）
    "reason": "string",             // 转人工原因
    "source": "rule|agent",         // 触发来源
    "transfer_time": "timestamp",   // 转人工时间
    "assigned_human": "string"      // 分配的人工客服
  },
  "created_at": "timestamp",
  "updated_at": "timestamp",
  "closed_at": "timestamp"
}
```

#### 4.2.2 会话超时处理

- 会话空闲超时自动关闭（可配置，默认30分钟）
- 智能体响应超时转人工（可配置，默认10秒）
- 人工客服响应超时提醒（可配置）

---

### 4.3 核心处理器模块 (Processor)

核心处理器是系统的消息处理中枢，消费 `inputs` 队列，按会话维度批量处理消息，发布结果到 `outputs` 队列。

#### 4.3.1 功能描述

**核心职责**：
- 消费 `inputs` 队列中的事件消息
- 获取会话级分布式锁，防止并发处理
- 从 Redis ZSET 获取会话的所有未处理消息
- **1️⃣ 事件处理**：处理 event 类型消息（结束对话、状态变更等）
- **2️⃣ 预校验**：对 message 类型消息进行规则预判断
- **3️⃣ 智能体调用**：调用智能体处理消息
- **4️⃣ 回复预处理**：判断是否强制转人工
- 发布处理结果到 `outputs` 队列

#### 4.3.2 核心处理流程

```mermaid
flowchart TD
    A["消费 inputs 队列"] --> B["获取 conversation_id"]
    B --> C["获取会话锁<br/>SETNX lock:conversation:{conv_id}"]
    C --> D{获取锁成功?}
    D -->|否| E["稍后重试"]
    D -->|是| F["ZRANGEBYSCORE<br/>input-messages:{conv_id}"]
    
    F --> G{有未处理消息?}
    G -->|否| H["释放锁，跳过"]
    G -->|是| I["解析消息列表"]
    
    I --> J["1️⃣ 事件处理层"]
    
    subgraph EventLayer["事件处理层 (event 类型)"]
        J --> J1{消息类型?}
        J1 -->|event:close| J2["处理关闭会话"]
        J1 -->|event:state_change| J3["同步状态变更"]
        J1 -->|message| J4["继续处理"]
        J2 --> JOut["发布 close_conversation<br/>到 outputs"]
        J3 --> J4
    end
    
    J4 --> K["2️⃣ 预校验层"]
    
    subgraph PreCheckLayer["预校验层 (message 类型)"]
        K --> K1{会话已转人工?}
        K1 -->|是| K2["跳过处理"]
        K1 -->|否| K3{内容要求转人工?}
        K3 -->|是| K4["发布 transfer_human<br/>到 outputs"]
        K3 -->|否| K5["继续处理"]
    end
    
    K5 --> L["3️⃣ 智能体处理层"]
    
    subgraph AgentLayer["智能体处理层"]
        L --> L1["获取智能体配置"]
        L1 --> L2["AgentFactory.create()"]
        L2 --> L3["agent.chat(messages)"]
        L3 --> L4{调用结果}
        L4 -->|成功| L5["获取回复"]
        L4 -->|失败/超时| L6["降级处理"]
        L6 --> L7{降级成功?}
        L7 -->|是| L5
        L7 -->|否| L8["发布 transfer_human<br/>到 outputs"]
    end
    
    L5 --> M["4️⃣ 回复预处理层"]
    
    subgraph PostLayer["回复预处理层"]
        M --> M1{回复强制转人工?}
        M1 -->|是| M2["发布 transfer_human<br/>到 outputs"]
        M1 -->|否| M3["发布 reply<br/>到 outputs"]
    end
    
    M2 --> N["ZREMRANGEBYSCORE 删除已处理"]
    M3 --> N
    K2 --> N
    K4 --> N
    JOut --> N
    L8 --> N
    
    N --> O["释放锁<br/>DEL lock:conversation:{conv_id}"]
    O --> P["ACK 确认消费"]

    %% 样式定义
    classDef inputStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef lockStyle fill:#FFECB3,stroke:#FF8F00,stroke-width:2px,color:#000
    classDef decisionStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef eventStyle fill:#E1BEE7,stroke:#8E24AA,stroke-width:2px,color:#000
    classDef preCheckStyle fill:#B3E5FC,stroke:#0288D1,stroke-width:2px,color:#000
    classDef agentStyle fill:#C8E6C9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef postStyle fill:#FFCCBC,stroke:#E64A19,stroke-width:2px,color:#000
    classDef outputStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000

    %% 应用样式
    class A,B inputStyle
    class C,D,O lockStyle
    class G,J1,K1,K3,L4,L7,M1 decisionStyle
    class J,J2,J3,JOut eventStyle
    class K,K2,K4,K5 preCheckStyle
    class L,L1,L2,L3,L5,L6,L8 agentStyle
    class M,M2,M3 postStyle
    class N,P outputStyle
```

#### 4.3.3 事件处理层

处理 event 类型的消息，同步外部状态变更到内部系统：

| 事件类型 | 处理逻辑 |
|----------|----------|
| `event:close_conversation` | 外部结束对话 → 发布 close_conversation 到 outputs |
| `event:transfer_human` | 外部触发转人工 → 发布 transfer_human 到 outputs |
| `event:state_change` | 外部状态变更 → 同步更新内部会话状态 |
| `event:user_enter` | 用户进入会话 → 可选发送欢迎语 |

**处理逻辑**：

- `event:close_conversation` → 发布 `close_conversation` 到 outputs
- `event:transfer_human` → 发布 `transfer_human` 到 outputs
- `event:state_change` → 同步更新内部会话状态
- `event:user_enter` → 可选发送欢迎语

#### 4.3.4 预校验层

对 message 类型消息进行规则预判断，快速处理明确的转人工请求：

**预校验规则**：

1. **会话已转人工** → 跳过处理，等待人工
2. **关键词匹配** → 直接转人工（如"转人工"、"人工客服"等）
3. **VIP策略** → 如配置了VIP用户直接转人工，则触发转人工
4. **继续处理** → 通过预校验，继续后续智能体处理

**预校验规则配置**：

```json
{
  "pre_check_rules": {
    "transfer_keywords": ["转人工", "人工客服", "找人工", "真人客服", "投诉"],
    "vip_direct_transfer": false,
    "max_agent_retries": 2,
    "agent_timeout_seconds": 30
  }
}
```

#### 4.3.5 消息处理主流程

**处理步骤**：

1. 消费 `inputs` 队列事件
2. 获取会话级分布式锁（防止并发处理）
3. 从 Redis ZSET 获取会话的所有未处理消息
4. 执行核心处理流程（事件处理 → 预校验 → 智能体调用 → 回复预处理）
5. 发布处理结果到 `outputs` 队列
6. 删除已处理的消息
7. 释放锁并确认消费

**核心处理流程**：

- **1️⃣ 事件处理层**：先处理 event 类型消息，如关闭会话则直接返回
- **2️⃣ 预校验层**：检查会话状态、关键词匹配、VIP策略等
- **3️⃣ 智能体处理层**：调用智能体处理消息，获取回复
- **4️⃣ 回复预处理层**：判断是否强制转人工，封装 OutputEvent

#### 4.3.6 转人工处理

转人工由 Processor 生成 `transfer_human` 类型的 OutputEvent，发布到 `outputs` 队列，由 Gateway 消费并执行实际的转人工操作。

**转人工触发来源**：

| 来源 | 触发条件 | source 值 |
|------|----------|-----------|
| **规则触发** | 关键词匹配、VIP策略、会话超时 | `rule` |
| **智能体建议** | 置信度低、情绪激动、复杂问题 | `agent` |
| **外部触发** | 渠道侧触发转人工事件 | `external` |
| **异常兜底** | 智能体超时/异常 | `rule` |

**OutputEvent 结构**：

| 字段 | 类型 | 说明 |
|------|------|------|
| type | string | 事件类型 (reply/transfer_human/close_conversation) |
| conversation_id | string | 会话ID |
| channel | string | 渠道类型 |
| reason | string? | 原因（转人工/关闭会话） |
| source | string? | 触发来源 (rule/agent/external) |
| messages | array? | 回复消息列表（reply类型） |
| priority | string | 优先级 (high/normal/low) |
| timestamp | int | 时间戳 |

#### 4.3.7 异常处理与兜底

```mermaid
flowchart TD
    A[调用智能体] --> B{响应状态}
    
    B -->|成功| C[正常处理]
    B -->|超时| D{重试次数}
    B -->|异常| D
    
    D -->|未超限| E[重试调用]
    D -->|已超限| F{有降级智能体?}
    
    E --> A
    
    F -->|有| G[调用降级智能体]
    F -->|无| H[发布 transfer_human 到 outputs]
    
    G --> I{降级结果}
    I -->|成功| C
    I -->|失败| H
    
    H --> J[记录异常日志]

    %% 样式定义
    classDef agentStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef decisionStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef successStyle fill:#C8E6C9,stroke:#2E7D32,stroke-width:2px,color:#000
    classDef retryStyle fill:#FFE082,stroke:#F57F17,stroke-width:2px,color:#000
    classDef fallbackStyle fill:#FFCCBC,stroke:#E64A19,stroke-width:2px,color:#000
    classDef errorStyle fill:#FFCDD2,stroke:#C62828,stroke-width:2px,color:#000

    %% 应用样式
    class A,G agentStyle
    class B,D,F,I decisionStyle
    class C successStyle
    class E retryStyle
    class H fallbackStyle
    class J errorStyle
```

**兜底策略配置**：

```json
{
  "fallback_strategy": {
    "max_retries": 2,
    "retry_delay_ms": 500,
    "timeout_seconds": 30,
    "on_all_fail": "transfer_human",
    "fallback_message": "抱歉，系统繁忙，正在为您转接人工客服..."
  }
}
```

**异常处理策略**：

1. **主智能体调用**：带重试机制（可配置重试次数和延迟）
2. **降级智能体**：主智能体失败后，尝试调用降级智能体
3. **最终兜底**：所有尝试失败后，发布 `transfer_human` 到 outputs 队列

---

### 4.4 智能体模块

智能体层采用 **统一接口 + 多种实现** 的架构设计，所有智能体都实现同一个 `IAgentAdapter` 接口，支持本地智能体、远程智能体、组合智能体三种模式。

#### 4.4.1 智能体架构

```mermaid
classDiagram
    class IAgentAdapter {
        <<interface>>
        +initialize(config) Promise void
        +chat(request) Promise ChatResponse
        +chatStream(request) AsyncIterable ChatChunk
        +healthCheck() Promise boolean
    }
    
    class LocalAgentAdapter {
        -model: LocalModel
        -faqStore: VectorStore
        +chat(request) Promise ChatResponse
    }
    
    class RemoteAgentAdapter {
        -apiClient: HttpClient
        -config: RemoteConfig
        +chat(request) Promise ChatResponse
    }
    
    class HybridAgentAdapter {
        -localAgent: LocalAgentAdapter
        -remoteAgent: RemoteAgentAdapter
        -router: IntentRouter
        +chat(request) Promise ChatResponse
    }
    
    class OpenAIAdapter {
        +chat(request) Promise ChatResponse
    }
    
    class QwenAdapter {
        +chat(request) Promise ChatResponse
    }
    
    class CozeAdapter {
        +chat(request) Promise ChatResponse
    }
    
    IAgentAdapter <|.. LocalAgentAdapter
    IAgentAdapter <|.. RemoteAgentAdapter
    IAgentAdapter <|.. HybridAgentAdapter
    RemoteAgentAdapter <|-- OpenAIAdapter
    RemoteAgentAdapter <|-- QwenAdapter
    RemoteAgentAdapter <|-- CozeAdapter
    HybridAgentAdapter o-- LocalAgentAdapter
    HybridAgentAdapter o-- RemoteAgentAdapter
```

```mermaid
flowchart TB
    subgraph AgentLayer["智能体层"]
        Interface["IAgentAdapter<br/>统一接口"]
        
        subgraph Implementations["实现类"]
            Local["LocalAgentAdapter<br/>本地智能体"]
            Remote["RemoteAgentAdapter<br/>远程智能体"]
            Hybrid["HybridAgentAdapter<br/>组合智能体"]
        end
    end
    
    subgraph LocalProviders["本地模型提供者"]
        Ollama["Ollama"]
        LlamaCpp["llama.cpp"]
        VLLM["vLLM"]
    end
    
    subgraph RemoteProviders["远程平台提供者"]
        OpenAI["OpenAI"]
        Qwen["通义千问"]
        Coze["Coze"]
        Dify["Dify"]
        CustomAPI["自定义API"]
    end
    
    Interface --> Implementations
    Local --> LocalProviders
    Remote --> RemoteProviders
    Hybrid --> Local
    Hybrid --> Remote
```

#### 4.4.2 统一接口定义

**智能体类型枚举**：
- `LOCAL` - 本地智能体
- `REMOTE` - 远程智能体
- `HYBRID` - 组合智能体

**ChatRequest 结构**：

| 字段 | 类型 | 说明 |
|------|------|------|
| conversation_id | string | 会话ID |
| message_type | string | 消息类型 (text/image/voice) |
| content | MessageContent | 消息内容 |
| history | array | 对话历史 |
| context | object | 上下文信息 |
| user_info | UserInfo | 用户信息 |
| timestamp | int | 时间戳 |

**ChatResponse 结构**：

| 字段 | 类型 | 说明 |
|------|------|------|
| reply | string | 回复内容 |
| reply_type | string | 回复类型 (text/rich) |
| rich_content | object? | 富文本内容 |
| action | object? | 需要执行的动作 |
| confidence | float | 置信度 0-1 |
| should_transfer | bool | 是否建议转人工（仅建议） |
| transfer_reason | string? | 转人工原因 |
| processed_by | string | 处理者标识 |
| metadata | object? | 扩展元数据 |

**IAgentAdapter 接口方法**：

| 方法 | 说明 |
|------|------|
| `type() -> AgentType` | 智能体类型标识 |
| `initialize(config) -> void` | 初始化配置 |
| `chat(request) -> ChatResponse` | 发送消息并获取回复 |
| `chat_stream(request) -> AsyncIterator` | 流式响应（可选实现） |
| `health_check() -> bool` | 健康检查 |
| `destroy() -> void` | 销毁/释放资源 |

#### 4.4.3 本地智能体 (LocalAgentAdapter)

**功能**：基于本地部署的模型提供智能对话能力，适合处理FAQ、简单咨询等场景。

```mermaid
flowchart LR
    Request[ChatRequest] --> LocalAgent[LocalAgentAdapter]
    LocalAgent --> Provider{模型提供者}
    Provider -->|Ollama| Ollama[Ollama API]
    Provider -->|llama.cpp| LlamaCpp[llama.cpp Server]
    Provider -->|vLLM| VLLM[vLLM Server]
    Ollama --> Response[ChatResponse]
    LlamaCpp --> Response
    VLLM --> Response

    %% 样式定义
    classDef inputStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef agentStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef decisionStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef providerStyle fill:#C8E6C9,stroke:#2E7D32,stroke-width:2px,color:#000
    classDef outputStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000

    %% 应用样式
    class Request inputStyle
    class LocalAgent agentStyle
    class Provider decisionStyle
    class Ollama,LlamaCpp,VLLM providerStyle
    class Response outputStyle
```

**支持的本地模型提供者**：

| 提供者 | 说明 | 适用场景 |
|--------|------|----------|
| Ollama | 本地模型服务，易于部署 | 开发/测试/小规模部署 |
| llama.cpp | C++推理引擎，性能优秀 | 高性能生产环境 |
| vLLM | 高吞吐推理服务 | 大规模并发场景 |

**配置示例**：

```json
{
  "agent_type": "local",
  "name": "本地客服助手",
  "config": {
    "provider": "ollama",
    "endpoint": "http://localhost:11434",
    "model": "qwen2:7b",
    "temperature": 0.7,
    "max_tokens": 512,
    "system_prompt": "你是一个专业的客服助手...",
    "timeout": 5000
  },
  "fallback_agent_id": "remote-openai"
}
```

#### 4.4.4 远程智能体 (RemoteAgentAdapter)

**功能**：对接远程AI平台API，获取强大的语言理解和生成能力。

**支持的远程平台**：

| 平台 | 优先级 | 说明 |
|------|--------|------|
| OpenAI | P0 | GPT-4, GPT-3.5 |
| Azure OpenAI | P0 | 企业版OpenAI |
| 通义千问 | P0 | 阿里云大模型 |
| 文心一言 | P1 | 百度大模型 |
| Coze | P1 | 字节跳动智能体平台 |
| Dify | P1 | 开源LLM应用平台 |
| 自定义HTTP | P1 | 通用HTTP接口 |

**配置示例**：

```json
{
  "agent_type": "remote",
  "name": "OpenAI GPT-4",
  "config": {
    "provider": "openai",
    "api_key": "sk-xxx",
    "api_base": "https://api.openai.com/v1",
    "model": "gpt-4",
    "temperature": 0.7,
    "max_tokens": 2000,
    "system_prompt": "你是一个专业的客服助手...",
    "timeout": 30000
  },
  "retry_config": {
    "max_retries": 3,
    "retry_delay": 1000
  }
}
```

#### 4.4.5 组合智能体 (HybridAgentAdapter)

**功能**：组合本地智能体和远程智能体，本地进行意图识别和简单问题处理，复杂问题路由到远程智能体。

```mermaid
flowchart TB
    Request[ChatRequest] --> Hybrid[HybridAgentAdapter]
    
    Hybrid --> Router[意图路由器]
    Router --> Classify{问题分类}
    
    Classify -->|FAQ/简单问题| Local[LocalAgentAdapter]
    Classify -->|复杂问题| Remote[RemoteAgentAdapter]
    Classify -->|转人工| Transfer[转人工处理]
    
    Local --> Response[ChatResponse]
    Remote --> Response
    
    Local -.->|本地失败| Fallback[降级处理]
    Remote -.->|远程失败| Fallback
    Fallback --> Response

    %% 样式定义
    classDef inputStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef hybridStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000
    classDef routerStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef decisionStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef localStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef remoteStyle fill:#FFE0B2,stroke:#E65100,stroke-width:2px,color:#000
    classDef transferStyle fill:#FFEBEE,stroke:#D32F2F,stroke-width:2px,color:#000
    classDef fallbackStyle fill:#FFCDD2,stroke:#C62828,stroke-width:2px,color:#000
    classDef outputStyle fill:#E0F2F1,stroke:#00796B,stroke-width:2px,color:#000

    %% 应用样式
    class Request inputStyle
    class Hybrid hybridStyle
    class Router routerStyle
    class Classify decisionStyle
    class Local localStyle
    class Remote remoteStyle
    class Transfer transferStyle
    class Fallback fallbackStyle
    class Response outputStyle
```

**意图分类规则**：

| 类别 | 说明 | 路由目标 |
|------|------|----------|
| `faq` | FAQ常见问题 | 本地智能体 |
| `simple_chat` | 简单对话/闲聊 | 本地智能体 |
| `product_inquiry` | 商品咨询 | 远程智能体 |
| `order_service` | 订单服务 | 远程智能体 |
| `complaint` | 投诉/复杂问题 | 远程智能体 |
| `transfer_human` | 明确要求转人工 | 转人工 |

**配置示例**：

```json
{
  "agent_type": "hybrid",
  "name": "组合客服智能体",
  "config": {
    "local_agent": {
      "provider": "ollama",
      "endpoint": "http://localhost:11434",
      "model": "qwen2:7b",
      "system_prompt": "你是一个专业的客服助手..."
    },
    "remote_agent": {
      "provider": "openai",
      "api_key": "sk-xxx",
      "model": "gpt-4",
      "system_prompt": "你是一个专业的客服助手..."
    },
    "router": {
      "type": "llm",
      "model": "qwen2:1.5b",
      "rules": [
        {
          "keywords": ["转人工", "人工客服"],
          "action": "transfer_human"
        },
        {
          "keywords": ["退款", "投诉"],
          "action": "remote"
        }
      ],
      "default_action": "local",
      "confidence_threshold": 0.8
    }
  },
  "fallback_strategy": {
    "local_fail": "remote",
    "remote_fail": "transfer_human"
  }
}
```

#### 4.4.6 智能体转人工判断

智能体在处理消息时，会根据多种因素判断是否建议转人工。**注意：智能体只返回建议，不执行转人工操作，实际执行由消息处理器推入队列，消息网关执行。**

**转人工判断逻辑**：

1. **置信度过低**：回答置信度 < 0.3 → 建议转人工
2. **负面情绪**：检测到用户情绪激动（sentiment=negative & intensity > 0.7）→ 建议转人工
3. **多轮未解决**：连续多轮对话未解决问题（unresolved_turns >= 3）→ 建议转人工
4. **复杂问题**：识别到复杂业务场景（投诉、退款纠纷等）→ 建议转人工
5. **敏感操作**：涉及需要人工确认的敏感操作 → 建议转人工

**转人工判断条件**：

| 条件 | 阈值/规则 | 原因 |
|------|----------|------|
| 置信度过低 | confidence < 0.3 | 回答可能不准确 |
| 负面情绪 | sentiment=negative & intensity > 0.7 | 用户情绪激动，需人工安抚 |
| 多轮未解决 | unresolved_turns >= 3 | 智能体无法有效解决问题 |
| 复杂问题类型 | intent in [complaint, refund_dispute...] | 需要人工判断和处理 |
| 敏感操作 | requires_human_verification = true | 涉及资金、隐私等敏感操作 |

#### 4.4.7 智能体工厂

智能体工厂根据配置创建对应的智能体实例：

**支持的远程适配器**：
- OpenAI → OpenAIAdapter
- Azure OpenAI → AzureOpenAIAdapter
- 通义千问 → QwenAdapter
- Coze → CozeAdapter
- Dify → DifyAdapter
- 自定义HTTP → CustomHttpAdapter

**创建流程**：
1. 根据 `agent_type` 判断类型（local/remote/hybrid）
2. 对于 remote 类型，根据 `provider` 创建对应的适配器
3. 初始化智能体配置
4. 返回智能体实例

#### 4.4.8 处理流程（含转人工分层协作）

```mermaid
sequenceDiagram
    participant User as 用户消息
    participant MP as 消息处理器(核心层)
    participant Factory as AgentFactory
    participant Agent as IAgentAdapter
    participant Transfer as 转人工执行
    participant Channel as 渠道API

    User->>MP: 用户消息
    activate MP
    MP->>MP: 规则预判断 preCheck()
    
    alt 关键词命中"转人工"
        MP->>Transfer: 触发转人工(source=rule)
        activate Transfer
    else 会话已转人工
        MP-->>User: 等待人工处理
    else 正常处理
        MP->>Factory: getAgent(agentId)
        activate Factory
        Factory-->>MP: agent实例
        deactivate Factory
        
        MP->>Agent: chat(request)
        activate Agent
        
        alt 调用成功
            Agent-->>MP: ChatResponse
            deactivate Agent
            
            alt should_transfer = true
                MP->>Transfer: 触发转人工(source=agent)
                activate Transfer
            else should_transfer = false
                MP-->>User: 发送回复
            end
            
        else 调用失败/超时
            deactivate Agent
            MP->>Factory: getFallbackAgent()
            activate Factory
            Factory-->>MP: fallbackAgent
            deactivate Factory
            MP->>Agent: chat(request)
            activate Agent
            
            alt 降级成功
                Agent-->>MP: ChatResponse
                deactivate Agent
                Note over MP: 检查 should_transfer
            else 降级失败
                deactivate Agent
                MP->>Transfer: 触发转人工(source=rule, reason=agent_fail)
                activate Transfer
            end
        end
    end
    
    Transfer->>Transfer: 更新会话状态
    Transfer-->>User: 发送提示消息
    Transfer->>Channel: 调用渠道转人工API
    activate Channel
    Channel-->>Transfer: 转接成功
    deactivate Channel
    deactivate Transfer
    deactivate MP

    Note over User,Channel: 分层协作，职责清晰
```

**说明**：
- **规则预判断**：核心处理层在调用智能体前进行，可快速处理明确的转人工请求
- **智能体建议**：智能体通过 `should_transfer` 返回建议，不执行转人工操作
- **统一执行**：转人工操作统一由核心处理层的 `Transfer` 模块执行

---

### 4.5 消息发送模块

#### 4.5.1 消息格式转换

**功能描述**：将智能体回复转换为目标渠道的消息格式

**转换流程**：
1. 接收统一格式的回复消息
2. 根据目标渠道获取对应适配器
3. 转换为渠道特定格式
4. 调用渠道API发送消息

#### 4.5.2 渠道发送接口

**企业微信发送**：
- 文本消息
- 图片消息
- 链接消息
- 小程序卡片

**淘宝发送**：
- 文本消息
- 图片消息
- 商品卡片
- 优惠券卡片

---

### 4.6 人工转接模块

#### 4.6.1 转接触发来源

转人工操作由 **Processor 生成 OutputEvent**，通过 `outputs` 队列传递给 **Gateway 执行**。触发来源分为三类：

| 来源类型 | 触发条件 | source 值 |
|----------|----------|-----------|
| **规则触发** | 关键词匹配、VIP策略、智能体异常 | `rule` |
| **智能体建议** | 置信度低、情绪激动、复杂问题 | `agent` |
| **外部触发** | 渠道侧推送转人工事件 | `external` |

**规则触发（Processor 预校验层）**：

| 触发条件 | 说明 |
|----------|------|
| 关键词匹配 | 用户发送"转人工"等关键词，不调用智能体 |
| 会话已转人工 | 会话状态为 transferred，跳过处理 |
| VIP策略 | 配置了VIP用户直接转人工 |
| 智能体异常 | 智能体超时/异常，兜底转人工 |

**智能体建议（智能体返回 shouldTransfer=true）**：

| 触发条件 | 说明 |
|----------|------|
| 置信度过低 | 回答置信度 < 0.3 |
| 负面情绪 | 检测到用户情绪激动 |
| 多轮未解决 | 连续多轮未解决用户问题 |
| 复杂问题 | 投诉、退款纠纷等复杂场景 |

#### 4.6.2 转接流程

```mermaid
flowchart TD
    A[用户消息] --> B[Processor 核心处理]
    
    B --> C{预校验}
    C -->|关键词命中| D["生成 transfer_human OutputEvent"]
    C -->|会话已转人工| E[跳过,等待人工]
    C -->|正常| F[调用智能体]
    
    F --> G[智能体处理]
    G --> H{检查响应}
    
    H -->|shouldTransfer=true| D
    H -->|shouldTransfer=false| I["生成 reply OutputEvent"]
    
    D --> J["发布到 outputs 队列"]
    I --> J
    
    J --> K[Gateway 消费 outputs]
    
    K --> L{消息类型}
    L -->|transfer_human| M[执行转人工]
    L -->|reply| N[发送回复]
    
    M --> O[发送提示消息]
    M --> P[调用渠道转人工API]
    M --> Q[更新会话状态]
    
    P --> R[人工客服接入]

    %% 样式定义
    classDef inputStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef processorStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef decisionStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef outputStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000
    classDef gatewayStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef transferStyle fill:#FFEBEE,stroke:#D32F2F,stroke-width:2px,color:#000
    classDef successStyle fill:#C8E6C9,stroke:#2E7D32,stroke-width:2px,color:#000

    %% 应用样式
    class A inputStyle
    class B,F,G processorStyle
    class C,H,L decisionStyle
    class D,I,J outputStyle
    class K,N gatewayStyle
    class M,O,P,Q transferStyle
    class E,R successStyle
```

#### 4.6.3 执行转人工（Gateway）

转人工操作由 **Gateway** 消费 `outputs` 队列后执行：

**执行步骤**：

1. 发送转人工提示消息（如"正在为您转接人工客服，请稍候..."）
2. 调用渠道转人工API（传递原因、优先级等信息）
3. 更新会话状态为 `transferred`，记录转人工原因、来源、时间
4. 记录转人工日志

#### 4.6.4 各渠道转接API

| 渠道 | 转接接口 | 支持功能 |
|------|----------|----------|
| **企业微信** | 转接会话到客服 | 指定客服、技能组分配 |
| **淘宝** | 千牛转接 | 指定客服分组 |
| **抖音** | 抖店转人工 | 队列分配 |

**渠道适配器接口方法**：

| 方法 | 说明 |
|------|------|
| `transferToHuman(conversationId, options) -> void` | 转接到人工客服 |
| `supportsCloseConversation() -> bool` | 是否支持关闭会话 |
| `closeConversation(conversationId) -> void` | 关闭会话 |

---

### 4.7 管理后台模块 (PHP Laravel + Filament)

管理后台使用 **PHP Laravel + Filament** 开发，提供可视化的配置管理和数据统计功能。

#### 4.7.1 技术选型

| 组件 | 技术 | 说明 |
|------|------|------|
| 框架 | Laravel 11 | PHP 现代框架 |
| 管理面板 | Filament 3.x | 快速构建Admin界面 |
| 数据库 | MySQL 8.0 | 与其他服务共享 |
| 缓存 | Redis | 配置缓存、会话缓存 |

#### 4.7.2 Filament 资源定义

**应用管理 (ApplicationResource)**：

- 表单字段：应用名称、应用描述、绑定智能体、启用状态
- 列表字段：应用ID、应用名称、状态、创建时间

**渠道配置 (ChannelResource)**：

- 表单字段：所属应用、渠道类型（企业微信/淘宝/抖音等）
- 根据渠道类型动态显示配置项：
  - 企业微信：企业ID、应用Secret、回调Token、加密Key
  - 淘宝：App Key、App Secret、Session Key

**智能体管理 (AgentResource)**：

- 表单字段：智能体名称、所属人、智能体类型
- 根据智能体类型动态显示配置：
  - 本地智能体：提供者（Ollama/llama.cpp/vLLM）、服务地址、模型名称
  - 远程智能体：提供者（OpenAI/Azure/通义千问/Coze/Dify）、API Key、API Base URL、模型
  - 通用配置：系统提示词、Temperature、Max Tokens、降级智能体

#### 4.7.3 数据统计仪表板

**统计指标**：

- 今日消息数（含趋势图表）
- 活跃会话数
- 智能体处理率
- 转人工率

**统计维度**：
- 消息总量（按时间段）
- 会话数量（按状态）
- 智能体处理量（按类型）
- 人工转接率
- 平均响应时间
- 用户满意度

**统计周期**：实时 / 小时 / 日 / 周 / 月

---

## 5. 非功能需求

### 5.1 性能要求

| 指标 | 要求 |
|------|------|
| 消息接收延迟 | < 100ms（接收到转发） |
| 本地智能体响应 | < 1s（P95） |
| 远程智能体响应 | < 5s（P95） |
| 组合智能体响应 | < 2s（本地分类+处理，P95） |
| 消息发送延迟 | < 200ms |
| 系统吞吐量 | > 1000 msg/s |
| 并发会话数 | > 10000 |

### 5.2 可用性要求

- 系统可用性：99.9%
- 数据持久化：消息不丢失
- 故障恢复：自动重试机制
- 降级策略：智能体不可用时自动转人工

### 5.3 安全要求

- 数据传输：HTTPS加密
- 敏感数据：加密存储（API密钥等）
- 访问控制：基于角色的权限管理
- 审计日志：操作日志记录
- 渠道验签：验证回调请求合法性

### 5.4 扩展性要求

- 新渠道接入：实现适配器接口即可
- 新智能体接入：实现智能体接口即可
- 水平扩展：支持多实例部署
- 配置热更新：无需重启更新配置

---

## 6. 接口设计

### 6.1 回调接口

#### 6.1.1 企业微信回调

```
POST /api/callback/wecom/{app_id}
```

#### 6.1.2 淘宝回调

```
POST /api/callback/taobao/{app_id}
```

### 6.2 管理接口

#### 6.2.1 应用管理

```
POST   /api/admin/apps              # 创建应用
GET    /api/admin/apps              # 应用列表
GET    /api/admin/apps/{app_id}     # 应用详情
PUT    /api/admin/apps/{app_id}     # 更新应用
DELETE /api/admin/apps/{app_id}     # 删除应用
```

#### 6.2.2 渠道配置

```
POST   /api/admin/apps/{app_id}/channels            # 添加渠道
GET    /api/admin/apps/{app_id}/channels            # 渠道列表
PUT    /api/admin/apps/{app_id}/channels/{id}       # 更新渠道配置
DELETE /api/admin/apps/{app_id}/channels/{id}       # 删除渠道
```

#### 6.2.3 智能体管理

```
POST   /api/admin/agents                            # 创建智能体
GET    /api/admin/agents                            # 智能体列表
GET    /api/admin/agents/{id}                       # 智能体详情
PUT    /api/admin/agents/{id}                       # 更新智能体
DELETE /api/admin/agents/{id}                       # 删除智能体
```

### 6.3 数据接口

```
GET /api/admin/stats/overview                       # 总览统计
GET /api/admin/stats/messages                       # 消息统计
GET /api/admin/stats/conversations                  # 会话统计
GET /api/admin/conversations                        # 会话列表
GET /api/admin/conversations/{conversation_id}      # 会话详情
GET /api/admin/conversations/{conversation_id}/messages  # 会话消息记录
```

---

## 7. 数据模型

### 7.1 实体关系图

```mermaid
erDiagram
    AGENT ||--o{ CHANNEL : serves
    APPLICATION ||--o{ CHANNEL : has
    APPLICATION ||--o{ CONVERSATION : has
    CHANNEL ||--o{ CONVERSATION : has
    CONVERSATION ||--o{ MESSAGE : contains

    AGENT {
        bigint id PK
        varchar agent_type
        varchar name
        varchar provider
        json config
        bigint fallback_agent_id FK
        bigint owner_id FK
        tinyint status
        datetime created_at
        datetime updated_at
    }

    APPLICATION {
        bigint id PK
        varchar app_id UK
        varchar app_name
        varchar app_secret
        text description
        tinyint status
        datetime created_at
        datetime updated_at
    }

    CHANNEL {
        bigint id PK
        bigint app_id FK
        bigint agent_id FK
        varchar channel
        json config
        tinyint status
        datetime created_at
        datetime updated_at
    }

    CONVERSATION {
        bigint id PK
        varchar conversation_id UK
        bigint app_id FK
        bigint channel_id FK
        varchar channel
        varchar channel_user_id
        varchar user_nickname
        boolean is_vip
        varchar status
        bigint current_agent_id FK
        json context
        varchar transfer_reason
        varchar transfer_source
        datetime transfer_time
        varchar assigned_human
        datetime created_at
        datetime updated_at
        datetime closed_at
    }

    MESSAGE {
        bigint id PK
        varchar message_id UK
        varchar conversation_id FK
        varchar app_id FK
        varchar channel
        tinyint direction
        varchar message_type
        json content
        varchar channel_message_id
        varchar sender_type
        bigint processed_by_agent_id FK
        datetime created_at
    }
```

### 7.2 核心实体

#### 智能体表 (agents)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| agent_type | varchar(20) | 智能体类型(local/remote/hybrid) |
| name | varchar(100) | 名称 |
| provider | varchar(20) | 提供者(ollama/openai/qwen/coze等) |
| config | json | 配置信息(加密) |
| fallback_agent_id | bigint | 降级智能体ID |
| owner_id | bigint | 所属人ID |
| status | tinyint | 状态 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

#### 应用表 (applications)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| app_id | varchar(32) | 应用ID |
| app_name | varchar(100) | 应用名称 |
| app_secret | varchar(64) | 应用密钥 |
| description | text | 描述 |
| agent_id | bigint | 绑定的智能体ID |
| status | tinyint | 状态 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

#### 渠道表 (channels)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| app_id | varchar(32) | 应用ID |
| channel | varchar(20) | 渠道类型 |
| config | json | 配置信息(加密) |
| status | tinyint | 状态 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

#### 会话表 (conversations)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| conversation_id | varchar(64) | 会话ID |
| app_id | varchar(32) | 应用ID |
| channel | varchar(20) | 渠道 |
| channel_user_id | varchar(64) | 渠道用户ID |
| user_nickname | varchar(100) | 用户昵称 |
| is_vip | boolean | 是否VIP用户 |
| status | varchar(20) | 会话状态 |
| current_agent_id | bigint | 当前使用的智能体ID |
| context | json | 会话上下文 |
| transfer_reason | varchar(100) | 转人工原因 |
| transfer_source | varchar(20) | 转人工来源(rule/agent) |
| transfer_time | datetime | 转人工时间 |
| assigned_human | varchar(64) | 分配的人工客服ID |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |
| closed_at | datetime | 关闭时间 |

#### 消息表 (messages)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| message_id | varchar(64) | 消息ID |
| conversation_id | varchar(64) | 会话ID |
| app_id | varchar(32) | 应用ID |
| channel | varchar(20) | 渠道 |
| direction | tinyint | 方向(1:收 2:发) |
| message_type | varchar(20) | 消息类型 |
| content | json | 消息内容 |
| channel_message_id | varchar(64) | 渠道消息ID |
| sender_type | varchar(20) | 发送者类型(user/agent/human) |
| processed_by_agent_id | bigint | 处理智能体ID |
| created_at | datetime | 创建时间 |

---

## 8. 部署架构

### 8.1 单机部署 (Docker Compose)

适用于开发测试和小规模使用：

```mermaid
flowchart TB
    subgraph Server["单机服务器 (Docker Compose)"]
        subgraph LaravelApp["Laravel 应用"]
            App["app<br/>(Web/API/Admin)<br/>:8080"]
        end
        
        subgraph QueueWorkers["队列消费者"]
            Processor["processor<br/>(消费 inputs)"]
            GatewayConsumer["gateway-consumer<br/>(消费 outputs)"]
        end
        
        subgraph LocalAI["本地AI"]
            Ollama["Ollama<br/>:11434"]
        end
        
        subgraph Data["数据服务"]
            MySQL[("MySQL<br/>:3306")]
            Redis[("Redis<br/>:6379")]
        end
    end
    
    App -->|"发布 inputs"| Redis
    Redis -->|"消费 inputs"| Processor
    Processor -->|"发布 outputs"| Redis
    Redis -->|"消费 outputs"| GatewayConsumer
    
    Processor --> Ollama
    
    App --> MySQL
    Processor --> MySQL
    GatewayConsumer --> MySQL

    %% 样式定义
    classDef appStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef processorStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef gatewayStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef aiStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef dbStyle fill:#E0F2F1,stroke:#00796B,stroke-width:3px,color:#000

    %% 应用样式
    class App appStyle
    class Processor processorStyle
    class GatewayConsumer gatewayStyle
    class Ollama aiStyle
    class MySQL,Redis dbStyle
```

**docker-compose.yml 示例**：

```yaml
version: '3.8'

services:
  # Laravel 应用 (Web + API + Admin)
  app:
    build:
      context: ./admin
      dockerfile: Dockerfile
    ports:
      - "8080:80"
    environment:
      - APP_ENV=local
      - APP_DEBUG=true
      - DB_CONNECTION=mysql
      - DB_HOST=mysql
      - DB_PORT=3306
      - DB_DATABASE=huizhida
      - DB_USERNAME=root
      - DB_PASSWORD=password
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - QUEUE_CONNECTION=redis
      - OLLAMA_HOST=http://ollama:11434
    volumes:
      - ./admin:/var/www/html
    depends_on:
      - mysql
      - redis

  # Processor 队列消费者 (消费 inputs 队列)
  processor:
    build:
      context: ./admin
      dockerfile: Dockerfile
    command: php artisan queue:process-inputs
    environment:
      - APP_ENV=local
      - DB_HOST=mysql
      - DB_DATABASE=huizhida
      - DB_USERNAME=root
      - DB_PASSWORD=password
      - REDIS_HOST=redis
      - OLLAMA_HOST=http://ollama:11434
    volumes:
      - ./admin:/var/www/html
    depends_on:
      - mysql
      - redis
      - ollama
    deploy:
      replicas: 2  # 可根据负载调整

  # Gateway 队列消费者 (消费 outputs 队列)
  gateway-consumer:
    build:
      context: ./admin
      dockerfile: Dockerfile
    command: php artisan queue:consume-outputs
    environment:
      - APP_ENV=local
      - DB_HOST=mysql
      - DB_DATABASE=huizhida
      - DB_USERNAME=root
      - DB_PASSWORD=password
      - REDIS_HOST=redis
    volumes:
      - ./admin:/var/www/html
    depends_on:
      - mysql
      - redis
    deploy:
      replicas: 2  # 可根据负载调整

  # Ollama 本地模型
  ollama:
    image: ollama/ollama:latest
    ports:
      - "11434:11434"
    volumes:
      - ollama_data:/root/.ollama
    # GPU支持 (可选)
    # deploy:
    #   resources:
    #     reservations:
    #       devices:
    #         - capabilities: [gpu]

  mysql:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=password
      - MYSQL_DATABASE=huizhida
    volumes:
      - mysql_data:/var/lib/mysql
    ports:
      - "3306:3306"

  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data

volumes:
  mysql_data:
  redis_data:
  ollama_data:
```

**服务说明**：

| 服务 | 说明 | 扩容方式 |
|------|------|----------|
| `app` | Laravel 主应用，处理 HTTP 请求（回调、管理后台） | 水平扩展 |
| `processor` | 消费 inputs 队列，处理消息 | 增加 replicas |
| `gateway-consumer` | 消费 outputs 队列，发送消息 | 增加 replicas |
| `ollama` | 本地 LLM 推理服务 | 需 GPU 支持 |

### 8.2 集群部署 (Kubernetes)

适用于生产环境：

```mermaid
flowchart TB
    LB["负载均衡<br/>Ingress / SLB"]
    
    subgraph AppCluster["Laravel 应用集群"]
        App1["app-1<br/>(Web/API)"]
        App2["app-2<br/>(Web/API)"]
        AppN["app-n<br/>(Web/API)"]
    end
    
    subgraph ProcessorCluster["Processor 消费者集群"]
        Proc1["processor-1"]
        Proc2["processor-2"]
        ProcN["processor-n"]
    end
    
    subgraph GatewayConsumerCluster["Gateway 消费者集群"]
        GWC1["gateway-consumer-1"]
        GWC2["gateway-consumer-2"]
    end
    
    subgraph LocalAICluster["本地AI集群"]
        Ollama1["ollama-1<br/>GPU节点"]
        Ollama2["ollama-2<br/>GPU节点"]
    end
    
    subgraph RemoteAI["远程智能体"]
        OpenAI["OpenAI"]
        Qwen["通义千问"]
        Coze["Coze"]
    end
    
    subgraph DataLayer["数据层"]
        MySQL[("MySQL 主从<br/>RDS")]
        Redis[("Redis 集群<br/>ElastiCache")]
        VectorDB[("向量数据库<br/>Milvus")]
    end
    
    LB --> AppCluster
    
    AppCluster -->|"发布 inputs"| Redis
    Redis -->|"消费 inputs"| ProcessorCluster
    ProcessorCluster -->|"发布 outputs"| Redis
    Redis -->|"消费 outputs"| GatewayConsumerCluster
    
    ProcessorCluster <--> LocalAICluster
    ProcessorCluster <--> RemoteAI
    ProcessorCluster <--> VectorDB
    
    AppCluster --> MySQL
    ProcessorCluster --> MySQL
    GatewayConsumerCluster --> MySQL

    %% 样式定义
    classDef lbStyle fill:#E1F5FE,stroke:#0277BD,stroke-width:3px,color:#000
    classDef appStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef processorStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef gatewayStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef localAIStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef remoteAIStyle fill:#FFE0B2,stroke:#E65100,stroke-width:2px,color:#000
    classDef dbStyle fill:#E0F2F1,stroke:#00796B,stroke-width:3px,color:#000

    %% 应用样式
    class LB lbStyle
    class App1,App2,AppN appStyle
    class Proc1,Proc2,ProcN processorStyle
    class GWC1,GWC2 gatewayStyle
    class Ollama1,Ollama2 localAIStyle
    class OpenAI,Qwen,Coze remoteAIStyle
    class MySQL,Redis,VectorDB dbStyle
```

### 8.3 服务扩缩容策略

| 服务 | 扩容条件 | 缩容条件 | 说明 |
|------|----------|----------|------|
| **app** (Laravel Web) | CPU > 70% 或 QPS > 1000 | CPU < 30% | 无状态，可水平扩展 |
| **processor** (inputs消费者) | inputs 队列积压 > 1000 | 队列空闲 | 按消费能力扩展 |
| **gateway-consumer** (outputs消费者) | outputs 队列积压 > 500 | 队列空闲 | 一般 2-4 副本即可 |
| **ollama** | GPU利用率 > 80% | - | 需 GPU 节点 |

**队列监控指标**：

| 指标 | 告警阈值 | 说明 |
|------|----------|------|
| `inputs` 队列长度 | > 1000 | 需扩容 processor |
| `outputs` 队列长度 | > 500 | 需扩容 gateway-consumer |
| 消息处理延迟 | > 5s | 检查智能体响应时间 |
| 消息发送失败率 | > 1% | 检查渠道 API 状态 |

### 8.4 项目目录结构

系统采用 **PHP Laravel Monorepo** 架构，通过 packages 实现模块化：

```
huizhida-chatbot/               # 汇智答
├── admin/                      # Laravel 主应用
│   ├── app/
│   │   ├── Filament/          # Filament 管理后台
│   │   │   ├── Resources/     # Filament资源
│   │   │   │   ├── ApplicationResource.php
│   │   │   │   ├── ChannelResource.php
│   │   │   │   └── AgentResource.php
│   │   │   └── Widgets/       # 仪表板组件
│   │   ├── Console/
│   │   │   └── Commands/      # 队列消费命令
│   │   ├── Models/            # Eloquent 模型
│   │   └── Providers/
│   │
│   ├── packages/              # 模块化包
│   │   │
│   │   ├── core/              # 核心包 - 共享DTO、接口、模型
│   │   │   ├── src/
│   │   │   │   ├── Domain/
│   │   │   │   │   ├── Conversation/
│   │   │   │   │   │   ├── DTO/
│   │   │   │   │   │   │   ├── ConversationDTO.php
│   │   │   │   │   │   │   ├── MessageDTO.php
│   │   │   │   │   │   │   └── ChannelMessage.php
│   │   │   │   │   │   ├── Contracts/
│   │   │   │   │   │   │   └── ConversationQueueInterface.php
│   │   │   │   │   │   └── Models/
│   │   │   │   │   └── Agent/
│   │   │   │   │       └── Contracts/
│   │   │   │   │           └── AgentAdapterInterface.php
│   │   │   │   └── Infrastructure/
│   │   │   │       └── Queue/
│   │   │   │           ├── RedisStreamQueue.php
│   │   │   │           └── RabbitMQQueue.php
│   │   │   └── composer.json
│   │   │
│   │   ├── gateway/           # 消息网关包
│   │   │   ├── src/
│   │   │   │   ├── Domain/
│   │   │   │   │   └── Contracts/
│   │   │   │   │       └── ChannelAdapterInterface.php
│   │   │   │   ├── Application/
│   │   │   │   │   └── Services/
│   │   │   │   │       └── CallbackService.php
│   │   │   │   ├── Infrastructure/
│   │   │   │   │   └── Adapters/
│   │   │   │   │       ├── WeComAdapter.php
│   │   │   │   │       ├── TaobaoAdapter.php
│   │   │   │   │       ├── DouyinAdapter.php
│   │   │   │   │       └── ChannelAdapterFactory.php
│   │   │   │   ├── Http/
│   │   │   │   │   └── Controllers/
│   │   │   │   │       └── CallbackController.php
│   │   │   │   └── Console/
│   │   │   │       └── Commands/
│   │   │   │           └── ConsumeOutputsCommand.php
│   │   │   ├── routes/
│   │   │   │   └── api.php
│   │   │   └── composer.json
│   │   │
│   │   └── agent-processor/   # 核心处理器包
│   │       ├── src/
│   │       │   ├── Domain/
│   │       │   │   ├── Contracts/
│   │       │   │   │   └── AgentAdapterInterface.php
│   │       │   │   └── Events/
│   │       │   │       └── OutputEvent.php
│   │       │   ├── Application/
│   │       │   │   └── Services/
│   │       │   │       ├── MessageProcessorService.php
│   │       │   │       ├── EventHandler.php
│   │       │   │       ├── PreCheckService.php
│   │       │   │       └── AgentInvoker.php
│   │       │   ├── Infrastructure/
│   │       │   │   └── Agents/
│   │       │   │       ├── LocalAgentAdapter.php
│   │       │   │       ├── RemoteAgentAdapter.php
│   │       │   │       ├── OpenAIAdapter.php
│   │       │   │       ├── CozeAdapter.php
│   │       │   │       └── AgentFactory.php
│   │       │   └── Console/
│   │       │       └── Commands/
│   │       │           └── ProcessConversationEventsCommand.php
│   │       └── composer.json
│   │
│   ├── config/
│   ├── database/
│   │   └── migrations/
│   ├── routes/
│   ├── resources/
│   ├── tests/
│   ├── composer.json
│   └── Dockerfile
│
├── docs/                       # 文档
│   └── requirements.md
├── docker-compose.yml          # 本地开发
├── docker-compose.prod.yml     # 生产部署
└── README.md
```

**包依赖关系**：

```mermaid
flowchart TB
    Admin["admin<br/>(Laravel主应用)"]
    Gateway["gateway<br/>(消息网关包)"]
    Processor["agent-processor<br/>(核心处理器包)"]
    Core["core<br/>(核心共享包)"]
    
    Admin --> Gateway
    Admin --> Processor
    Gateway --> Core
    Processor --> Core
    
    classDef mainApp fill:#E3F2FD,stroke:#1976D2,stroke-width:2px
    classDef package fill:#E8F5E9,stroke:#388E3C,stroke-width:2px
    classDef core fill:#FFF3E0,stroke:#F57C00,stroke-width:2px
    
    class Admin mainApp
    class Gateway,Processor package
    class Core core
```

---

## 9. 附录

### 9.1 参考文档

**渠道对接**：
- [企业微信客服API文档](https://developer.work.weixin.qq.com/document/path/94638)
- [淘宝开放平台文档](https://open.taobao.com/)

**远程智能体**：
- [OpenAI API文档](https://platform.openai.com/docs/)
- [通义千问API文档](https://help.aliyun.com/document_detail/2400395.html)
- [Coze开放平台文档](https://www.coze.cn/docs/)
- [Dify文档](https://docs.dify.ai/)

**本地模型**：
- [Ollama官方文档](https://ollama.ai/)
- [llama.cpp项目](https://github.com/ggerganov/llama.cpp)
- [vLLM文档](https://docs.vllm.ai/)

