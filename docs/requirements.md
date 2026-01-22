# 汇智答-智能客服平台 

## 1. 文档信息

| 项目 | 内容 |
|------|------|
| 项目名称 | 汇智答 (HuiZhiDa) |
| 英文名称 | HuiZhiDa ChatBot |
| 项目代号 | HZD |
| 文档版本 | v2.2.0 |
| 创建日期 | 2026-01-20 |
| 文档状态 | 架构定稿 |
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
| 应用(Application) | 一个独立的客服服务实例，绑定一个智能体，可配置多个渠道 |
| 回调(Callback) | 渠道主动推送消息到本系统的接口 |

---

## 3. 系统架构

### 3.1 整体架构图

系统采用 **三层微服务架构**，将不同职责分离到独立的服务中：

```mermaid
flowchart TB
    subgraph Clients["客户端渠道"]
        WeChat["企业微信"]
        Taobao["淘宝"]
        Douyin["抖音"]
        Others["其他渠道"]
    end

    subgraph GatewayService["消息网关服务 (Golang)"]
        subgraph Adapters["渠道适配器"]
            WeChatAdapter["企微适配器"]
            TaobaoAdapter["淘宝适配器"]
            DouyinAdapter["抖音适配器"]
            GenericAdapter["通用适配器"]
        end
        Transformer["消息格式转换"]
        ConversationWriter["会话记录"]
        MsgSender["消息发送器"]
    end

    subgraph MessageQueue["消息队列 (MQ)"]
        InQueue["incoming_messages<br/>待处理队列"]
        OutQueue["outgoing_messages<br/>待发送队列"]
        TransferQueue["transfer_requests<br/>转人工队列"]
    end

    subgraph ProcessorService["消息处理器服务 (Python)"]
        Processor["消息处理器"]
        PreCheck["规则预判断"]
        
        subgraph AgentLayer["智能体层"]
            AgentInterface["IAgentAdapter<br/>统一接口"]
            subgraph Implementations["智能体实现"]
                LocalAgent["本地智能体"]
                RemoteAgent["远程智能体"]
                HybridAgent["组合智能体"]
            end
        end
        
        subgraph AICapabilities["AI能力"]
            RAG["FAQ检索<br/>LangChain"]
            Intent["意图识别"]
            Emotion["情绪分析"]
        end
    end

    subgraph AdminService["管理后台服务 (PHP Laravel)"]
        Filament["Filament Admin"]
        AppMgr["应用管理"]
        ConfigMgr["配置管理"]
        StatsMgr["数据统计"]
    end

    subgraph Storage["数据存储层"]
        MySQL[("MySQL<br/>主数据库")]
        Redis2[("Redis<br/>缓存")]
        MQ[("消息队列<br/>MQ")]
        VectorDB[("向量数据库<br/>Chroma/Milvus")]
    end

    subgraph LocalAI["本地AI服务"]
        Ollama["Ollama"]
    end

    subgraph RemoteAI["远程智能体平台"]
        OpenAI["OpenAI"]
        Qwen["通义千问"]
        Coze["Coze"]
    end

    %% 客户端到网关
    WeChat --> WeChatAdapter
    Taobao --> TaobaoAdapter
    Douyin --> DouyinAdapter
    Others --> GenericAdapter

    %% 网关内部流程
    Adapters --> Transformer
    Transformer --> ConversationWriter
    ConversationWriter --> InQueue

    %% 队列到处理器
    InQueue --> Processor
    Processor --> PreCheck
    PreCheck --> AgentLayer
    AgentLayer --> AICapabilities

    %% 处理器输出到队列
    Processor --> OutQueue
    Processor --> TransferQueue

    %% 队列到网关发送
    OutQueue --> MsgSender
    TransferQueue --> MsgSender
    MsgSender --> Clients

    %% 智能体连接
    LocalAgent --> Ollama
    RemoteAgent --> RemoteAI
    AICapabilities --> VectorDB

    %% 数据存储
    GatewayService --> MySQL
    GatewayService --> Redis2
    ProcessorService --> MySQL
    ProcessorService --> Redis2
    AdminService --> MySQL
    AdminService --> Redis2

    %% 样式定义
    classDef clientStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef gatewayStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef queueStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000
    classDef processorStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef adminStyle fill:#FCE4EC,stroke:#C2185B,stroke-width:2px,color:#000
    classDef storageStyle fill:#E0F2F1,stroke:#00796B,stroke-width:3px,color:#000
    classDef aiStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000

    %% 应用样式
    class WeChat,Taobao,Douyin,Others clientStyle
    class WeChatAdapter,TaobaoAdapter,DouyinAdapter,GenericAdapter,Transformer,ConversationWriter,MsgSender gatewayStyle
    class InQueue,OutQueue,TransferQueue queueStyle
    class Processor,PreCheck,LocalAgent,RemoteAgent,HybridAgent,AgentInterface,RAG,Intent,Emotion processorStyle
    class Filament,AppMgr,ConfigMgr,StatsMgr adminStyle
    class MySQL,Redis2,MQ,VectorDB storageStyle
    class Ollama,OpenAI,Qwen,Coze aiStyle
```

### 3.2 服务职责划分

| 服务 | 技术栈 | 核心职责 |
|------|--------|----------|
| **消息网关** | Golang (Gin/Fiber) | 渠道回调接收、签名验证、消息格式转换、会话记录、消息入队、消息发送、转人工执行 |
| **消息处理器** | Python (FastAPI) | 消息消费、规则预判断、智能体调用、AI能力集成（RAG/意图/情绪）、智能体适配器开发 |
| **管理后台** | PHP Laravel + Filament | 应用管理、渠道配置、智能体配置、数据统计、系统监控 |

### 3.3 技术栈

| 服务 | 技术选型 | 说明 |
|------|----------|------|
| **消息网关** | Go + Gin/Fiber | 高并发消息处理，渠道回调接收 |
| **消息处理器** | Python + FastAPI | AI生态丰富，智能体开发便捷 |
| **管理后台** | PHP Laravel + Filament | 快速开发管理界面，功能完善 |
| **消息队列** | Redis Streams / RabbitMQ / Kafka | 服务间消息传递，可配置选择 |
| **数据库** | MySQL 8.0 | 持久化存储，事务支持 |
| **缓存** | Redis | 会话缓存、配置缓存、分布式锁 |
| **向量数据库** | Chroma / Milvus | FAQ语义检索，知识库 |
| **本地模型** | Ollama | 本地LLM推理，意图识别 |
| **AI框架** | LangChain / LlamaIndex | RAG、对话编排 |
| **部署** | Docker + Docker Compose / K8s | 容器化部署 |

### 3.4 服务间通信

```mermaid
flowchart LR
    subgraph Gateway["消息网关 (Go)"]
        G1["接收回调"]
        G2["发送消息"]
    end

    subgraph Queue["消息队列 (MQ)"]
        Q1["incoming_messages"]
        Q2["outgoing_messages"]
        Q3["transfer_requests"]
    end

    subgraph Processor["消息处理器 (Python)"]
        P1["消费消息"]
        P2["智能体调用"]
    end

    subgraph Admin["管理后台 (Laravel)"]
        A1["配置管理"]
    end

    subgraph DB["共享存储"]
        MySQL[("MySQL")]
        Redis2[("Redis")]
    end

    G1 -->|入队| Q1
    Q1 -->|消费| P1
    P1 --> P2
    P2 -->|回复入队| Q2
    P2 -->|转人工入队| Q3
    Q2 -->|消费| G2
    Q3 -->|消费| G2

    Gateway <-->|读写| DB
    Processor <-->|读写| DB
    Admin <-->|读写| DB

    %% 样式定义
    classDef gatewayStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef queueStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000
    classDef processorStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef adminStyle fill:#FCE4EC,stroke:#C2185B,stroke-width:2px,color:#000
    classDef dbStyle fill:#E0F2F1,stroke:#00796B,stroke-width:3px,color:#000

    %% 应用样式
    class G1,G2 gatewayStyle
    class Q1,Q2,Q3 queueStyle
    class P1,P2 processorStyle
    class A1 adminStyle
    class MySQL,Redis2 dbStyle
```

**支持的消息中间件**：

| 中间件 | 适用场景 | 说明 |
|--------|----------|------|
| **Redis Streams** | 小规模/开发测试 | 简单易用，低延迟 |
| **RabbitMQ** | 中等规模/生产环境 | 功能丰富，可靠性高 |
| **Kafka** | 大规模/高吞吐 | 高吞吐，持久化强 |

**队列定义**：

| 队列名 | 方向 | 消息内容 | 说明 |
|--------|------|----------|------|
| `incoming_messages` | Gateway → Processor | 统一格式消息 | 待处理的用户消息 |
| `outgoing_messages` | Processor → Gateway | 回复消息 | 智能体回复，需发送到渠道 |
| `transfer_requests` | Processor → Gateway | 转人工请求 | 需调用渠道转人工API |

**MQ 抽象接口**：

```python
# Python MQ 接口
from abc import ABC, abstractmethod
from typing import Any, AsyncIterator

class MessageQueue(ABC):
    """消息队列抽象接口"""
    
    @abstractmethod
    async def publish(self, queue: str, message: Any) -> None:
        """发布消息到队列"""
        pass
    
    @abstractmethod
    async def subscribe(self, queue: str) -> AsyncIterator[Any]:
        """订阅队列消息"""
        pass
    
    @abstractmethod
    async def ack(self, message: Any) -> None:
        """确认消息消费"""
        pass
    
    @abstractmethod
    async def nack(self, message: Any) -> None:
        """拒绝消息，重新入队"""
        pass

# 具体实现
class RedisMQ(MessageQueue): ...
class RabbitMQ(MessageQueue): ...
class KafkaMQ(MessageQueue): ...
```

```go
// Go MQ 接口
type MessageQueue interface {
    Publish(queue string, message interface{}) error
    Subscribe(queue string) <-chan Message
    Ack(message Message) error
    Nack(message Message) error
    Close() error
}

// 具体实现
type RedisMQ struct { ... }
type RabbitMQ struct { ... }
type KafkaMQ struct { ... }
```

**消息格式示例**：

```json
// incoming_messages
{
  "message_id": "msg_001",
  "conversation_id": "conv_001",
  "app_id": "app_001",
  "channel": "wecom",
  "content": { "text": "你好" },
  "timestamp": 1705747200000
}

// outgoing_messages
{
  "message_id": "reply_001",
  "conversation_id": "conv_001",
  "channel": "wecom",
  "reply": "您好，请问有什么可以帮您？",
  "reply_type": "text"
}

// transfer_requests
{
  "conversation_id": "conv_001",
  "channel": "wecom",
  "reason": "用户请求转人工",
  "source": "rule",
  "priority": "normal"
}
```

---

## 4. 功能需求

### 4.1 消息网关模块 (Golang)

消息网关是系统的入口服务，使用 **Golang** 开发，负责与各客服渠道的直接交互。

#### 4.1.1 核心职责

| 职责 | 说明 |
|------|------|
| **渠道回调接收** | 接收各渠道推送的客服消息 |
| **签名验证** | 验证渠道请求的合法性 |
| **消息格式转换** | 将渠道消息转为统一格式 |
| **会话记录** | 创建/更新会话信息 |
| **消息入队** | 将待处理消息推入消息队列 |
| **消息发送** | 消费回复队列，调用渠道API发送 |
| **转人工执行** | 消费转人工队列，调用渠道转人工API |

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

```mermaid
sequenceDiagram
    participant P as 客服渠道
    participant G as 消息网关(Go)
    participant Q as 消息队列(MQ)
    participant S as MySQL/Redis

    P->>G: POST /callback/{channel}/{app_id}
    activate G
    G->>G: 验证签名/Token
    G->>G: 解析渠道消息格式
    G->>G: 转换为统一格式
    G->>S: 创建/更新会话
    activate S
    S-->>G: 会话信息
    deactivate S
    G->>S: 保存消息记录
    activate S
    S-->>G: 保存成功
    deactivate S
    G->>Q: 推入 incoming_messages 队列
    activate Q
    Q-->>G: 入队成功
    deactivate Q
    G-->>P: 200 OK (快速响应)
    deactivate G

    Note over P,G: 快速响应，异步处理
    Note over G,Q: 消息队列解耦
```

**Go代码示例**：

```go
// 渠道回调处理 - Gin框架
func (h *CallbackHandler) HandleCallback(c *gin.Context) {
    channel := c.Param("channel")
    appID := c.Param("app_id")
    
    // 1. 获取渠道适配器
    adapter, err := h.adapterFactory.Get(channel)
    if err != nil {
        c.JSON(400, gin.H{"error": "unsupported channel"})
        return
    }
    
    // 2. 验证签名
    if !adapter.VerifySignature(c.Request) {
        c.JSON(403, gin.H{"error": "invalid signature"})
        return
    }
    
    // 3. 解析并转换消息
    rawData, _ := io.ReadAll(c.Request.Body)
    message, err := adapter.ParseMessage(rawData)
    if err != nil {
        c.JSON(400, gin.H{"error": "parse error"})
        return
    }
    message.AppID = appID
    message.Channel = channel
    
    // 4. 更新会话
    conversation, err := h.conversationService.GetOrCreate(message)
    if err != nil {
        c.JSON(500, gin.H{"error": "conversation error"})
        return
    }
    message.ConversationID = conversation.ID
    
    // 5. 保存消息记录
    h.messageService.Save(message)
    
    // 6. 推入待处理队列 (通过 MQ 接口)
    h.mq.Publish("incoming_messages", message)
    
    // 7. 快速响应渠道
    c.JSON(200, adapter.GetSuccessResponse())
}
```

#### 4.1.3 消息发送（消费回复队列）

消息网关负责消费 `outgoing_messages` 队列，将智能体回复发送到对应渠道。

```mermaid
sequenceDiagram
    participant Q as 消息队列(MQ)
    participant G as 消息网关(Go)
    participant P as 客服渠道
    participant S as MySQL

    loop 持续消费
        Q->>G: 消费 outgoing_messages
        activate G
        G->>G: 获取渠道适配器
        G->>G: 转换为渠道消息格式
        G->>P: 调用渠道发送API
        activate P
        P-->>G: 发送结果
        deactivate P
        G->>S: 更新消息发送状态
        activate S
        S-->>G: 更新成功
        deactivate S
        deactivate G
    end

    Note over Q,G: 异步消费，高并发处理
```

```go
// 消息发送消费者 (通过 MQ 接口消费)
func (c *MessageSender) Start(ctx context.Context) {
    // 订阅 outgoing_messages 队列
    msgChan := c.mq.Subscribe("outgoing_messages")
    
    for {
        select {
        case <-ctx.Done():
            return
        case msg := <-msgChan:
            // 获取适配器并发送
            adapter, _ := c.adapterFactory.Get(msg.Channel)
            channelMsg := adapter.ConvertToChannelFormat(msg)
            
            if err := adapter.SendMessage(channelMsg); err != nil {
                // 发送失败，重新入队或记录
                c.handleSendError(msg, err)
            } else {
                // 更新发送状态
                c.messageService.UpdateStatus(msg.ID, "sent")
                c.mq.Ack(msg)  // 确认消费
            }
        }
    }
}
```

#### 4.1.4 转人工执行（消费转人工队列）

```go
// 转人工执行消费者 (通过 MQ 接口消费)
func (c *TransferExecutor) Start(ctx context.Context) {
    // 订阅 transfer_requests 队列
    reqChan := c.mq.Subscribe("transfer_requests")
    
    for {
        select {
        case <-ctx.Done():
            return
        case req := <-reqChan:
            // 获取适配器
            adapter, _ := c.adapterFactory.Get(req.Channel)
            
            // 发送提示消息
            tipMsg := c.config.GetTransferTipMessage()
            adapter.SendMessage(req.ConversationID, tipMsg)
            
            // 调用渠道转人工API
            if err := adapter.TransferToHuman(req); err != nil {
                c.handleTransferError(req, err)
            } else {
                c.mq.Ack(req)  // 确认消费
            }
            
            // 更新会话状态
            c.conversationService.UpdateStatus(req.ConversationID, "pending_human", req.Reason)
        }
    }
}
```

#### 4.1.5 统一消息格式

**消息结构定义**（Go struct）：

```go
type UnifiedMessage struct {
    MessageID         string            `json:"message_id"`
    AppID             string            `json:"app_id"`
    Channel           string            `json:"channel"`
    ChannelMessageID string            `json:"channel_message_id"`
    ConversationID    string            `json:"conversation_id"`
    User              UserInfo          `json:"user"`
    MessageType       string            `json:"message_type"`
    Content           MessageContent    `json:"content"`
    Timestamp         int64             `json:"timestamp"`
    RawData           json.RawMessage   `json:"raw_data,omitempty"`
}

type UserInfo struct {
    ChannelUserID string   `json:"channel_user_id"`
    Nickname       string   `json:"nickname"`
    Avatar         string   `json:"avatar"`
    IsVIP          bool     `json:"is_vip"`
    Tags           []string `json:"tags"`
}

type MessageContent struct {
    Text      string                 `json:"text,omitempty"`
    MediaURL  string                 `json:"media_url,omitempty"`
    MediaType string                 `json:"media_type,omitempty"`
    Extra     map[string]interface{} `json:"extra,omitempty"`
}
```

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

### 4.3 消息处理器模块 (Python)

消息处理器是系统的核心AI处理组件，使用 **Python + FastAPI** 开发，充分利用Python丰富的AI生态。

#### 4.3.1 功能描述

**核心职责**：
- 消费消息队列中的待处理消息
- 获取会话上下文
- **规则预判断**：在调用智能体前进行快速规则匹配
- 调用智能体层处理消息（利用LangChain/LlamaIndex等AI框架）
- 将回复消息推入发送队列
- 将转人工请求推入转人工队列

**Python技术优势**：
- LangChain/LlamaIndex 原生支持
- 丰富的AI/ML库支持
- OpenAI/Ollama SDK 完整
- 向量数据库集成便捷
- 异步支持好（FastAPI + asyncio）

#### 4.3.2 转人工判断的分层协作

转人工判断采用 **规则预判断 + 智能体建议 + 核心层执行** 的分层协作模式：

```mermaid
flowchart TD
    A[用户消息] --> B[核心处理层]
    
    B --> C{规则预判断}
    
    C -->|关键词命中| D[直接转人工]
    C -->|会话已转人工| E[跳过智能体,等待人工]
    C -->|智能体异常/超时| D
    C -->|正常| F[调用智能体]
    
    F --> G[智能体处理]
    G --> H{智能体返回}
    
    H -->|should_transfer=true| I[核心层执行转人工]
    H -->|should_transfer=false| J[核心层发送回复]
    
    D --> K[执行转人工操作]
    I --> K
    
    K --> L[调用渠道转人工API]
    K --> M[更新会话状态]
    K --> N[发送提示消息]

    %% 样式定义
    classDef inputStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef processStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef decisionStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef agentStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef transferStyle fill:#FFEBEE,stroke:#D32F2F,stroke-width:2px,color:#000
    classDef actionStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000

    %% 应用样式
    class A inputStyle
    class B processStyle
    class C,H decisionStyle
    class F,G agentStyle
    class D,I,K transferStyle
    class E,J,L,M,N actionStyle
```

**职责划分**：

| 层级 | 职责 | 判断类型 |
|------|------|----------|
| **核心处理层** | 规则预判断 + 执行转人工操作 | 关键词匹配、会话状态、异常兜底 |
| **智能体层** | 返回转人工建议（不执行） | 语义理解、情绪分析、置信度判断 |

#### 4.3.3 规则预判断

在调用智能体之前，消息处理器先进行规则预判断，可快速处理明确的转人工请求，减少不必要的智能体调用。

```python
from enum import Enum
from dataclasses import dataclass
from typing import Optional

class PreCheckAction(Enum):
    CALL_AGENT = "call_agent"
    TRANSFER_HUMAN = "transfer_human"
    SKIP = "skip"

@dataclass
class PreCheckResult:
    action: PreCheckAction
    reason: Optional[str] = None

def pre_check(
    message: UnifiedMessage, 
    conversation: Conversation, 
    config: AppConfig
) -> PreCheckResult:
    """规则预判断"""
    
    # 1. 会话已转人工，跳过智能体
    if conversation.status == "transferred":
        return PreCheckResult(PreCheckAction.SKIP, "already_transferred")
    
    # 2. 关键词匹配，直接转人工
    transfer_keywords = config.transfer_keywords or ["转人工", "人工客服", "找人工", "真人客服"]
    text = message.content.text or ""
    if any(kw in text for kw in transfer_keywords):
        return PreCheckResult(PreCheckAction.TRANSFER_HUMAN, "keyword_match")
    
    # 3. VIP用户直接转人工策略（可配置）
    if conversation.is_vip and config.vip_direct_transfer:
        return PreCheckResult(PreCheckAction.TRANSFER_HUMAN, "vip_policy")
    
    # 4. 正常调用智能体
    return PreCheckResult(PreCheckAction.CALL_AGENT)
```

**预判断规则配置**：

```json
{
  "pre_check_rules": {
    "transfer_keywords": ["转人工", "人工客服", "找人工", "真人客服", "投诉"],
    "vip_direct_transfer": false,
    "max_agent_retries": 2,
    "agent_timeout_action": "transfer_human"
  }
}
```

#### 4.3.4 处理流程

```mermaid
flowchart TD
    A[从MQ消费消息] --> B[获取/创建会话上下文]
    B --> C[规则预判断 preCheck]
    
    C --> D{预判断结果}
    
    D -->|skip| E[跳过,等待人工]
    D -->|transfer_human| F[推入转人工队列]
    D -->|call_agent| G[获取智能体实例]
    
    G --> H[AgentFactory.create]
    H --> I[agent.chat]
    
    I --> J{调用结果}
    
    J -->|成功| K{检查 should_transfer}
    J -->|失败/超时| L[获取降级智能体]
    
    K -->|true| F
    K -->|false| M[推入回复队列]
    
    L --> N[fallbackAgent.chat]
    N --> O{降级结果}
    O -->|成功| K
    O -->|失败| F
    
    M --> P[outgoing_messages]
    F --> Q[transfer_requests]
    
    P --> R[更新会话状态]
    Q --> R

    %% 样式定义
    classDef inputStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef processStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef decisionStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef agentStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef transferStyle fill:#FFEBEE,stroke:#D32F2F,stroke-width:2px,color:#000
    classDef queueStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000
    classDef actionStyle fill:#E0F2F1,stroke:#00796B,stroke-width:2px,color:#000

    %% 应用样式
    class A inputStyle
    class B,C,G,H processStyle
    class D,J,K,O decisionStyle
    class I,N agentStyle
    class F transferStyle
    class E,M,P,Q queueStyle
    class R actionStyle
```

**Python 消息消费主循环**：

```python
import asyncio
from app.mq import MessageQueue  # MQ 抽象接口

async def message_consumer(mq: MessageQueue):
    """消息消费主循环"""
    # 订阅 incoming_messages 队列
    async for message_data in mq.subscribe("incoming_messages"):
        try:
            message = UnifiedMessage.parse_raw(message_data)
            
            # 处理消息
            await process_message(message, mq)
            
            # 确认消费
            await mq.ack(message_data)
            
        except Exception as e:
            logger.error(f"消息处理异常: {e}")
            await mq.nack(message_data)  # 消费失败，重新入队

async def process_message(message: UnifiedMessage, mq: MessageQueue):
    """处理单条消息"""
    # 1. 获取会话上下文
    conversation = await conversation_service.get_or_create(message)
    
    # 2. 规则预判断
    check_result = pre_check(message, conversation, app_config)
    
    if check_result.action == PreCheckAction.SKIP:
        return  # 跳过，等待人工
    
    if check_result.action == PreCheckAction.TRANSFER_HUMAN:
        await request_transfer_human(conversation, TransferRequest(
            conversation_id=conversation.id,
            channel=conversation.channel,
            reason=check_result.reason,
            source="rule"
        ), mq)
        return
    
    # 3. 获取应用绑定的智能体
    agent_config = await agent_repository.get_by_id(app_config.agent_id)
    agent = AgentFactory.create(agent_config)
    await agent.initialize(agent_config)
    
    try:
        response = await asyncio.wait_for(
            agent.chat(build_chat_request(message, conversation)),
            timeout=app_config.agent_timeout
        )
        
        # 4. 处理响应
        if response.should_transfer:
            await request_transfer_human(conversation, TransferRequest(
                conversation_id=conversation.id,
                channel=conversation.channel,
                reason=response.transfer_reason or "agent_suggestion",
                source="agent"
            ), mq)
        else:
            # 推入回复队列 (通过 MQ 接口)
            await mq.publish("outgoing_messages", {
                "conversation_id": conversation.id,
                "channel": conversation.channel,
                "reply": response.reply,
                "reply_type": response.reply_type
            })
        
        # 5. 更新会话
        await conversation_service.update(conversation.id, {"updated_at": datetime.now()})
        
    except asyncio.TimeoutError:
        # 超时降级处理
        await handle_agent_timeout(conversation, mq)
```

#### 4.3.5 转人工处理

消息处理器检测到需要转人工时，将请求推入转人工队列，由消息网关（Go）负责实际执行。

```python
from dataclasses import dataclass
from typing import Optional, Literal
from datetime import datetime

@dataclass
class TransferRequest:
    conversation_id: str
    channel: str
    reason: str
    source: Literal["rule", "agent"]  # 触发来源
    agent_transfer_reason: Optional[str] = None
    priority: Literal["high", "normal", "low"] = "normal"

async def request_transfer_human(
    conversation: Conversation,
    request: TransferRequest,
    mq: MessageQueue
) -> None:
    """
    请求转人工 - 推入队列，由消息网关执行
    """
    # 1. 更新会话状态
    await conversation_manager.update(
        conversation.id,
        status="pending_human",
        transfer_reason=request.reason,
        transfer_source=request.source,
        transfer_time=datetime.now()
    )
    
    # 2. 构建转人工请求
    transfer_data = {
        "conversation_id": conversation.id,
        "channel": conversation.channel,
        "reason": request.reason,
        "source": request.source,
        "agent_reason": request.agent_transfer_reason,
        "priority": request.priority if not conversation.is_vip else "high",
        "context": conversation.context,
        "timestamp": datetime.now().isoformat()
    }
    
    # 3. 推入转人工队列，由消息网关消费并执行 (通过 MQ 接口)
    await mq.publish("transfer_requests", transfer_data)
    
    # 4. 记录转人工日志
    await log_transfer(transfer_data)
```

#### 4.3.6 异常处理与兜底

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
    F -->|无| H[执行转人工]
    
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
    "timeout_ms": 10000,
    "on_all_fail": "transfer_human",
    "fallback_message": "抱歉，系统繁忙，正在为您转接人工客服..."
  }
}
```

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

#### 4.4.2 统一接口定义 (Python)

```python
from abc import ABC, abstractmethod
from enum import Enum
from dataclasses import dataclass, field
from typing import Optional, List, Dict, Any, AsyncIterator

class AgentType(Enum):
    """智能体类型枚举"""
    LOCAL = "local"      # 本地智能体
    REMOTE = "remote"    # 远程智能体
    HYBRID = "hybrid"    # 组合智能体

@dataclass
class UserInfo:
    """用户信息"""
    channel_user_id: str
    nickname: str
    avatar: Optional[str] = None
    is_vip: bool = False
    tags: List[str] = field(default_factory=list)

@dataclass
class MessageContent:
    """消息内容"""
    text: Optional[str] = None
    media_url: Optional[str] = None
    media_type: Optional[str] = None

@dataclass
class ChatRequest:
    """聊天请求"""
    conversation_id: str
    message_type: str                    # text|image|voice|...
    content: MessageContent
    history: List[Dict[str, Any]]        # 对话历史
    context: Dict[str, Any]              # 上下文信息
    user_info: UserInfo
    timestamp: int

@dataclass
class ChatResponse:
    """
    聊天响应
    注意：智能体只返回建议，不执行转人工操作
    转人工的实际执行由消息处理器推入队列，消息网关执行
    """
    reply: str                           # 回复内容
    reply_type: str = "text"             # text | rich
    rich_content: Optional[Dict] = None  # 富文本内容
    action: Optional[Dict] = None        # 需要执行的动作
    confidence: float = 1.0              # 置信度 0-1
    should_transfer: bool = False        # 是否建议转人工（仅建议）
    transfer_reason: Optional[str] = None
    processed_by: str = ""               # 处理者标识
    metadata: Optional[Dict] = None


class IAgentAdapter(ABC):
    """
    智能体适配器接口
    所有智能体实现都必须遵循此接口
    """
    
    @property
    @abstractmethod
    def type(self) -> AgentType:
        """智能体类型标识"""
        pass
    
    @abstractmethod
    async def initialize(self, config: Dict[str, Any]) -> None:
        """初始化配置"""
        pass
    
    @abstractmethod
    async def chat(self, request: ChatRequest) -> ChatResponse:
        """发送消息并获取回复"""
        pass
    
    async def chat_stream(self, request: ChatRequest) -> AsyncIterator[str]:
        """流式响应（可选实现）"""
        response = await self.chat(request)
        yield response.reply
    
    @abstractmethod
    async def health_check(self) -> bool:
        """健康检查"""
        pass
    
    async def destroy(self) -> None:
        """销毁/释放资源"""
        pass
```

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

```python
from dataclasses import dataclass
from typing import Optional

@dataclass
class TransferRecommendation:
    should_transfer: bool
    reason: Optional[str] = None
    message: Optional[str] = None

def evaluate_transfer_need(
    request: ChatRequest,
    response: GeneratedResponse,
    context: AgentContext
) -> TransferRecommendation:
    """
    智能体内部判断转人工的逻辑
    返回建议，由消息处理器决定是否执行
    """
    
    # 1. 置信度过低
    if response.confidence < 0.3:
        return TransferRecommendation(
            should_transfer=True,
            reason="low_confidence",
            message="无法确定回答的准确性"
        )
    
    # 2. 识别到强烈负面情绪
    if context.sentiment == "negative" and context.sentiment_intensity > 0.7:
        return TransferRecommendation(
            should_transfer=True,
            reason="negative_emotion",
            message="检测到用户情绪激动"
        )
    
    # 3. 多轮对话未解决问题
    if context.unresolved_turns >= 3:
        return TransferRecommendation(
            should_transfer=True,
            reason="unresolved_issue",
            message="多轮对话未能解决用户问题"
        )
    
    # 4. 识别到复杂业务场景
    complex_intents = ["complaint", "refund_dispute", "legal_issue"]
    if context.intent in complex_intents:
        return TransferRecommendation(
            should_transfer=True,
            reason="complex_issue",
            message=f"识别到复杂问题类型: {context.intent}"
        )
    
    # 5. 涉及敏感操作
    if context.requires_human_verification:
        return TransferRecommendation(
            should_transfer=True,
            reason="sensitive_operation",
            message="涉及需要人工确认的敏感操作"
        )
    
    return TransferRecommendation(should_transfer=False)
```

**转人工判断条件**：

| 条件 | 阈值/规则 | 原因 |
|------|----------|------|
| 置信度过低 | confidence < 0.3 | 回答可能不准确 |
| 负面情绪 | sentiment=negative & intensity > 0.7 | 用户情绪激动，需人工安抚 |
| 多轮未解决 | unresolved_turns >= 3 | 智能体无法有效解决问题 |
| 复杂问题类型 | intent in [complaint, refund_dispute...] | 需要人工判断和处理 |
| 敏感操作 | requires_human_verification = true | 涉及资金、隐私等敏感操作 |

#### 4.4.7 智能体工厂

```python
from typing import Dict, Any

class AgentFactory:
    """
    智能体工厂类
    根据配置创建对应的智能体实例
    """
    
    _remote_adapters = {
        "openai": "OpenAIAdapter",
        "azure": "AzureOpenAIAdapter",
        "qwen": "QwenAdapter",
        "coze": "CozeAdapter",
        "dify": "DifyAdapter",
    }
    
    @classmethod
    def create(cls, config: Dict[str, Any]) -> IAgentAdapter:
        """创建智能体实例"""
        agent_type = config.get("agent_type")
        
        if agent_type == "local":
            return LocalAgentAdapter(config)
        elif agent_type == "remote":
            return cls._create_remote_agent(config)
        elif agent_type == "hybrid":
            return HybridAgentAdapter(config)
        else:
            raise ValueError(f"Unknown agent type: {agent_type}")
    
    @classmethod
    def _create_remote_agent(cls, config: Dict[str, Any]) -> RemoteAgentAdapter:
        """创建远程智能体实例"""
        provider = config.get("config", {}).get("provider")
        
        if provider == "openai":
            return OpenAIAdapter(config)
        elif provider == "azure":
            return AzureOpenAIAdapter(config)
        elif provider == "qwen":
            return QwenAdapter(config)
        elif provider == "coze":
            return CozeAdapter(config)
        elif provider == "dify":
            return DifyAdapter(config)
        else:
            return CustomHttpAdapter(config)


# 使用示例
async def get_agent_for_app(app_id: str) -> IAgentAdapter:
    """获取应用绑定的智能体"""
    # 从数据库获取应用配置
    app = await application_repository.get_by_app_id(app_id)
    
    # 获取应用绑定的智能体配置
    agent_config = await agent_repository.get_by_id(app.agent_id)
    
    # 创建智能体实例
    agent = AgentFactory.create(agent_config)
    await agent.initialize(agent_config)
    
    return agent
```

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

转人工操作由 **核心处理层统一执行**，触发来源分为两类：

**规则触发（核心处理层预判断）**：

| 触发条件 | 判断位置 | 说明 |
|----------|----------|------|
| 关键词匹配 | 核心处理层 | 用户发送"转人工"等关键词，不调用智能体 |
| 会话已转人工 | 核心处理层 | 会话状态为 transferred，跳过智能体 |
| VIP策略 | 核心处理层 | 配置了VIP用户直接转人工 |
| 智能体异常 | 核心处理层 | 智能体超时/异常，兜底转人工 |

**智能体建议（智能体返回 should_transfer=true）**：

| 触发条件 | 判断位置 | 说明 |
|----------|----------|------|
| 置信度过低 | 智能体层 | 回答置信度 < 0.3 |
| 负面情绪 | 智能体层 | 检测到用户情绪激动 |
| 多轮未解决 | 智能体层 | 连续多轮未解决用户问题 |
| 复杂问题 | 智能体层 | 投诉、退款纠纷等复杂场景 |
| 敏感操作 | 智能体层 | 涉及资金、隐私等敏感操作 |

#### 4.6.2 转接流程

```mermaid
flowchart TD
    A[用户消息] --> B[核心处理层]
    
    B --> C{规则预判断}
    C -->|关键词命中| D[触发转人工]
    C -->|会话已转人工| E[跳过,等待人工]
    C -->|正常| F[调用智能体]
    
    F --> G[智能体处理]
    G --> H{检查响应}
    
    H -->|should_transfer=true| D
    H -->|should_transfer=false| I[发送回复]
    
    D --> J[核心层执行转人工]
    
    J --> K[更新会话状态]
    J --> L[发送提示消息]
    J --> M{转接模式}
    
    M -->|队列分配| N[渠道分配接口]
    M -->|指定客服| O[渠道指定接口]
    
    N --> P[人工客服接入]
    O --> P

    %% 样式定义
    classDef inputStyle fill:#E3F2FD,stroke:#1976D2,stroke-width:2px,color:#000
    classDef processStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef decisionStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef agentStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef transferStyle fill:#FFEBEE,stroke:#D32F2F,stroke-width:2px,color:#000
    classDef actionStyle fill:#F3E5F5,stroke:#7B1FA2,stroke-width:2px,color:#000
    classDef channelStyle fill:#E0F2F1,stroke:#00796B,stroke-width:2px,color:#000
    classDef successStyle fill:#C8E6C9,stroke:#2E7D32,stroke-width:2px,color:#000

    %% 应用样式
    class A inputStyle
    class B processStyle
    class C,H,M decisionStyle
    class F,G agentStyle
    class D,J transferStyle
    class E,I,K,L actionStyle
    class N,O channelStyle
    class P successStyle
```

#### 4.6.3 执行转人工（消息网关）

转人工操作由 **消息网关（Go）** 统一执行，消息处理器（Python）只负责将转人工请求推入队列：

```go
// 消息网关中的转人工执行器 (Go)
type TransferExecution struct {
    ConversationID    string `json:"conversation_id"`
    Source           string `json:"source"`            // rule | agent
    Reason           string `json:"reason"`
    AgentReason      string `json:"agent_reason,omitempty"`
    Mode             string `json:"mode"`              // queue | specific
    SpecificServicer string `json:"specific_servicer,omitempty"`
    Priority         string `json:"priority"`          // high | normal | low
}

func (e *TransferExecutor) Execute(ctx context.Context, params TransferExecution) error {
    // 1. 获取会话信息
    conversation, err := e.conversationService.Get(params.ConversationID)
    if err != nil {
        return err
    }
    
    // 2. 发送提示消息
    tipMsg := e.config.GetTransferTipMessage()
    adapter, _ := e.adapterFactory.Get(conversation.Channel)
    adapter.SendMessage(conversation.ID, tipMsg)
    
    // 3. 调用渠道转人工API
    if params.Mode == "queue" {
        err = adapter.TransferToQueue(conversation.ID, params.Priority)
    } else {
        err = adapter.TransferToSpecific(conversation.ID, params.SpecificServicer, params.Priority)
    }
    if err != nil {
        return err
    }
    
    // 4. 更新会话状态
    e.conversationService.Update(params.ConversationID, map[string]interface{}{
        "status":          "pending_human",
        "transfer_reason": params.Reason,
        "transfer_source": params.Source,
        "transfer_time":   time.Now(),
    })
    
    // 5. 记录转人工日志
    e.logService.RecordTransfer(params)
    
    return nil
}
```

#### 4.6.3 各渠道转接API

**企业微信**：
- 接口：转接会话到客服
- 支持：指定客服、技能组分配

**淘宝**：
- 接口：千牛转接
- 支持：指定客服分组

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

```php
<?php

namespace App\Filament\Resources;

use App\Models\Application;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationLabel = '应用管理';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('app_name')
                ->label('应用名称')
                ->required()
                ->maxLength(100),
                
            Forms\Components\Textarea::make('description')
                ->label('应用描述')
                ->rows(3),
                
            Forms\Components\Select::make('agent_id')
                ->label('绑定智能体')
                ->relationship('agent', 'name')
                ->searchable()
                ->required(),
                
            Forms\Components\Toggle::make('status')
                ->label('启用状态')
                ->default(true),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('app_id')->label('应用ID'),
            Tables\Columns\TextColumn::make('app_name')->label('应用名称'),
            Tables\Columns\IconColumn::make('status')->boolean()->label('状态'),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->label('创建时间'),
        ]);
    }
}
```

#### 4.7.3 渠道配置 (ChannelResource)

```php
<?php

namespace App\Filament\Resources;

use App\Models\Channel;
use Filament\Forms;
use Filament\Resources\Resource;

class ChannelResource extends Resource
{
    protected static ?string $model = Channel::class;
    protected static ?string $navigationLabel = '渠道配置';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\Select::make('app_id')
                ->label('所属应用')
                ->relationship('application', 'app_name')
                ->required(),
                
            Forms\Components\Select::make('channel')
                ->label('渠道类型')
                ->options([
                    'wecom' => '企业微信',
                    'taobao' => '淘宝/天猫',
                    'douyin' => '抖音',
                    'jd' => '京东',
                    'pdd' => '拼多多',
                    'webhook' => '自定义Webhook',
                ])
                ->reactive()
                ->required(),
                
            // 企业微信配置
            Forms\Components\Section::make('企业微信配置')
                ->schema([
                    Forms\Components\TextInput::make('config.corp_id')
                        ->label('企业ID'),
                    Forms\Components\TextInput::make('config.secret')
                        ->label('应用Secret')
                        ->password(),
                    Forms\Components\TextInput::make('config.token')
                        ->label('回调Token'),
                    Forms\Components\TextInput::make('config.encoding_aes_key')
                        ->label('加密Key'),
                ])
                ->visible(fn ($get) => $get('channel') === 'wecom'),
                
            // 淘宝配置
            Forms\Components\Section::make('淘宝配置')
                ->schema([
                    Forms\Components\TextInput::make('config.app_key')
                        ->label('App Key'),
                    Forms\Components\TextInput::make('config.app_secret')
                        ->label('App Secret')
                        ->password(),
                    Forms\Components\TextInput::make('config.session_key')
                        ->label('Session Key'),
                ])
                ->visible(fn ($get) => $get('channel') === 'taobao'),
        ]);
    }
}
```

#### 4.7.4 智能体管理 (AgentResource)

```php
<?php

namespace App\Filament\Resources;

use App\Models\Agent;
use Filament\Forms;
use Filament\Resources\Resource;

class AgentResource extends Resource
{
    protected static ?string $model = Agent::class;
    protected static ?string $navigationLabel = '智能体管理';

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('智能体名称')
                ->required(),
                
            Forms\Components\Select::make('owner_id')
                ->label('所属人')
                ->relationship('owner', 'name')
                ->searchable()
                ->required(),
                
            Forms\Components\Select::make('agent_type')
                ->label('智能体类型')
                ->options([
                    'local' => '本地智能体',
                    'remote' => '远程智能体',
                    'hybrid' => '组合智能体',
                ])
                ->reactive()
                ->required(),
                
            // 本地智能体配置
            Forms\Components\Section::make('本地智能体配置')
                ->schema([
                    Forms\Components\Select::make('config.provider')
                        ->label('提供者')
                        ->options([
                            'ollama' => 'Ollama',
                            'llama_cpp' => 'llama.cpp',
                            'vllm' => 'vLLM',
                        ]),
                    Forms\Components\TextInput::make('config.endpoint')
                        ->label('服务地址')
                        ->default('http://localhost:11434'),
                    Forms\Components\TextInput::make('config.model')
                        ->label('模型名称')
                        ->default('qwen2:7b'),
                ])
                ->visible(fn ($get) => $get('agent_type') === 'local'),
                
            // 远程智能体配置
            Forms\Components\Section::make('远程智能体配置')
                ->schema([
                    Forms\Components\Select::make('config.provider')
                        ->label('提供者')
                        ->options([
                            'openai' => 'OpenAI',
                            'azure' => 'Azure OpenAI',
                            'qwen' => '通义千问',
                            'coze' => 'Coze',
                            'dify' => 'Dify',
                        ]),
                    Forms\Components\TextInput::make('config.api_key')
                        ->label('API Key')
                        ->password(),
                    Forms\Components\TextInput::make('config.api_base')
                        ->label('API Base URL'),
                    Forms\Components\TextInput::make('config.model')
                        ->label('模型'),
                ])
                ->visible(fn ($get) => $get('agent_type') === 'remote'),
                
            // 通用配置
            Forms\Components\Section::make('通用配置')
                ->schema([
                    Forms\Components\Textarea::make('config.system_prompt')
                        ->label('系统提示词')
                        ->rows(5),
                    Forms\Components\TextInput::make('config.temperature')
                        ->label('Temperature')
                        ->numeric()
                        ->default(0.7),
                    Forms\Components\TextInput::make('config.max_tokens')
                        ->label('Max Tokens')
                        ->numeric()
                        ->default(2000),
                    Forms\Components\Select::make('fallback_agent_id')
                        ->label('降级智能体')
                        ->relationship('fallbackAgent', 'name'),
                ]),
        ]);
    }
}
```

#### 4.7.5 数据统计仪表板

```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('今日消息数', $this->getTodayMessageCount())
                ->description('较昨日 +12%')
                ->color('success')
                ->chart([7, 3, 4, 5, 6, 3, 5, 8]),
                
            Stat::make('活跃会话', $this->getActiveConversationCount())
                ->description('当前在线')
                ->color('primary'),
                
            Stat::make('智能体处理率', $this->getAgentHandleRate() . '%')
                ->description('AI自动处理比例')
                ->color('info'),
                
            Stat::make('转人工率', $this->getTransferRate() . '%')
                ->description('需人工介入比例')
                ->color('warning'),
        ];
    }
}
```

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
    AGENT ||--o{ APPLICATION : serves
    APPLICATION ||--o{ CHANNEL : has
    APPLICATION ||--o{ CONVERSATION : has
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
        bigint agent_id FK
        tinyint status
        datetime created_at
        datetime updated_at
    }

    CHANNEL {
        bigint id PK
        varchar app_id FK
        varchar channel
        json config
        tinyint status
        datetime created_at
        datetime updated_at
    }

    CONVERSATION {
        bigint id PK
        varchar conversation_id UK
        varchar app_id FK
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
        subgraph Gateway["消息网关 (Go)"]
            GatewayApp["hzd-gateway<br/>:8080"]
        end
        
        subgraph Processor["消息处理器 (Python)"]
            ProcessorApp["hzd-processor<br/>:8081"]
        end
        
        subgraph Admin["管理后台 (Laravel)"]
            AdminApp["hzd-admin<br/>:8082"]
        end
        
        subgraph LocalAI["本地AI"]
            Ollama["Ollama<br/>:11434"]
        end
        
        subgraph Data["数据服务"]
            MySQL[("MySQL<br/>:3306")]
            Redis[("Redis<br/>:6379")]
        end
    end
    
    GatewayApp -->|入队| Redis
    Redis -->|消费| ProcessorApp
    ProcessorApp -->|回复入队| Redis
    Redis -->|消费发送| GatewayApp
    
    ProcessorApp --> Ollama
    
    GatewayApp --> MySQL
    ProcessorApp --> MySQL
    AdminApp --> MySQL
    AdminApp --> Redis

    %% 样式定义
    classDef gatewayStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef processorStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef adminStyle fill:#FCE4EC,stroke:#C2185B,stroke-width:2px,color:#000
    classDef aiStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef dbStyle fill:#E0F2F1,stroke:#00796B,stroke-width:3px,color:#000

    %% 应用样式
    class GatewayApp gatewayStyle
    class ProcessorApp processorStyle
    class AdminApp adminStyle
    class Ollama aiStyle
    class MySQL,Redis dbStyle
```

**docker-compose.yml 示例**：

```yaml
version: '3.8'

services:
  # 消息网关 (Go)
  gateway:
    build: ./gateway
    ports:
      - "8080:8080"
    environment:
      - MYSQL_DSN=root:password@tcp(mysql:3306)/ai_cs
      - REDIS_ADDR=redis:6379
    depends_on:
      - mysql
      - redis

  # 消息处理器 (Python)
  processor:
    build: ./processor
    ports:
      - "8081:8081"
    environment:
      - DATABASE_URL=mysql+asyncmy://root:password@mysql:3306/ai_cs
      - REDIS_URL=redis://redis:6379
      - OLLAMA_HOST=http://ollama:11434
    depends_on:
      - mysql
      - redis
      - ollama

  # 管理后台 (Laravel)
  admin:
    build: ./admin
    ports:
      - "8082:80"
    environment:
      - DB_HOST=mysql
      - DB_DATABASE=ai_cs
      - REDIS_HOST=redis
    depends_on:
      - mysql
      - redis

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
      - MYSQL_DATABASE=ai_cs
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

### 8.2 集群部署 (Kubernetes)

适用于生产环境：

```mermaid
flowchart TB
    LB["负载均衡<br/>Ingress / SLB"]
    
    subgraph GatewayCluster["消息网关集群 (Go)"]
        GW1["gateway-1"]
        GW2["gateway-2"]
        GWN["gateway-n"]
    end
    
    subgraph ProcessorCluster["消息处理器集群 (Python)"]
        Proc1["processor-1"]
        Proc2["processor-2"]
        ProcN["processor-n"]
    end
    
    subgraph AdminCluster["管理后台 (Laravel)"]
        Admin1["admin-1"]
        Admin2["admin-2"]
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
        OSS[("对象存储<br/>S3/OSS")]
    end
    
    LB --> GatewayCluster
    LB --> AdminCluster
    
    GatewayCluster <-->|消息队列| DataLayer
    ProcessorCluster <-->|消息队列| DataLayer
    
    ProcessorCluster <--> LocalAICluster
    ProcessorCluster <--> RemoteAI
    ProcessorCluster <--> VectorDB
    
    GatewayCluster --> MySQL
    ProcessorCluster --> MySQL
    AdminCluster --> MySQL

    %% 样式定义
    classDef lbStyle fill:#E1F5FE,stroke:#0277BD,stroke-width:3px,color:#000
    classDef gatewayStyle fill:#FFF3E0,stroke:#F57C00,stroke-width:2px,color:#000
    classDef processorStyle fill:#E8F5E9,stroke:#388E3C,stroke-width:2px,color:#000
    classDef adminStyle fill:#FCE4EC,stroke:#C2185B,stroke-width:2px,color:#000
    classDef localAIStyle fill:#FFF9C4,stroke:#F9A825,stroke-width:2px,color:#000
    classDef remoteAIStyle fill:#FFE0B2,stroke:#E65100,stroke-width:2px,color:#000
    classDef dbStyle fill:#E0F2F1,stroke:#00796B,stroke-width:3px,color:#000

    %% 应用样式
    class LB lbStyle
    class GW1,GW2,GWN gatewayStyle
    class Proc1,Proc2,ProcN processorStyle
    class Admin1,Admin2 adminStyle
    class Ollama1,Ollama2 localAIStyle
    class OpenAI,Qwen,Coze remoteAIStyle
    class MySQL,Redis,VectorDB,OSS dbStyle
```

### 8.3 服务扩缩容策略

| 服务 | 扩容条件 | 缩容条件 | 说明 |
|------|----------|----------|------|
| 消息网关 (Go) | CPU > 70% 或 QPS > 5000 | CPU < 30% | 无状态，可水平扩展 |
| 消息处理器 (Python) | 队列积压 > 1000 | 队列空闲 | 按消费能力扩展 |
| 管理后台 (Laravel) | 并发 > 100 | 并发 < 20 | 一般 2 副本即可 |
| Ollama | GPU利用率 > 80% | - | 需 GPU 节点 |

### 8.4 项目目录结构

```
huizhida/                       # 汇智答
├── gateway/                    # 消息网关 (Go)
│   ├── cmd/
│   │   └── main.go
│   ├── internal/
│   │   ├── adapter/           # 渠道适配器
│   │   │   ├── wecom.go
│   │   │   ├── taobao.go
│   │   │   └── factory.go
│   │   ├── handler/           # HTTP处理器
│   │   ├── service/           # 业务服务
│   │   ├── queue/             # 队列操作
│   │   └── model/             # 数据模型
│   ├── pkg/
│   ├── configs/
│   ├── go.mod
│   └── Dockerfile
│
├── processor/                  # 消息处理器 (Python)
│   ├── app/
│   │   ├── main.py            # FastAPI入口
│   │   ├── core/
│   │   │   ├── processor.py   # 消息处理器
│   │   │   └── precheck.py    # 规则预判断
│   │   ├── agent/
│   │   │   ├── base.py        # IAgentAdapter
│   │   │   ├── local.py       # 本地智能体
│   │   │   ├── remote/        # 远程智能体
│   │   │   │   ├── openai.py
│   │   │   │   ├── qwen.py
│   │   │   │   └── coze.py
│   │   │   ├── hybrid.py      # 组合智能体
│   │   │   └── factory.py     # 智能体工厂
│   │   ├── ai/
│   │   │   ├── rag.py         # RAG检索
│   │   │   ├── intent.py      # 意图识别
│   │   │   └── emotion.py     # 情绪分析
│   │   ├── models/            # SQLAlchemy模型
│   │   ├── schemas/           # Pydantic模型
│   │   └── services/
│   ├── tests/
│   ├── configs/
│   ├── requirements.txt
│   └── Dockerfile
│
├── admin/                      # 管理后台 (Laravel + Filament)
│   ├── app/
│   │   ├── Filament/
│   │   │   ├── Resources/     # Filament资源
│   │   │   │   ├── ApplicationResource.php
│   │   │   │   ├── ChannelResource.php
│   │   │   │   └── AgentResource.php
│   │   │   └── Widgets/       # 仪表板组件
│   │   ├── Models/
│   │   └── Services/
│   ├── resources/
│   ├── routes/
│   ├── composer.json
│   └── Dockerfile
│
├── docs/                       # 文档
│   └── requirements.md
├── docker-compose.yml          # 本地开发
├── docker-compose.prod.yml     # 生产部署
└── README.md
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

