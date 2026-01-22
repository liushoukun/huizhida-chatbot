# 汇智答 - 消息网关服务 (Gateway)

消息网关服务是汇智答智能客服平台的核心入口服务，负责接收各渠道的回调消息、消息格式转换、会话管理和消息发送。

## 功能特性

- ✅ **企业微信渠道完整支持**
  - 签名验证（回调请求合法性验证）
  - 消息解析（企业微信消息格式转统一格式）
  - 消息发送（调用企业微信发送消息 API）
  - 转人工功能（转接到客服队列或指定客服）
  - Access Token 自动管理和缓存
- ✅ 统一消息格式转换
- ✅ 会话管理（创建、更新、状态管理）
- ✅ 消息队列集成（Redis Streams）
- ✅ 异步消息发送
- ✅ 转人工执行
- ✅ 优雅关闭

## 技术栈

- **语言**: Go 1.21+
- **Web框架**: Gin
- **数据库**: MySQL (GORM)
- **缓存/队列**: Redis (go-redis)
- **日志**: Zap
- **配置**: Viper

## 项目结构

```
gateway/
├── cmd/
│   └── main.go              # 主程序入口
├── internal/
│   ├── adapter/             # 渠道适配器
│   │   ├── adapter.go       # 适配器接口和工厂
│   │   ├── wecom.go         # 企业微信适配器
│   │   └── ...
│   ├── config/              # 配置管理
│   │   └── config.go
│   ├── handler/             # HTTP 处理器
│   │   ├── callback.go      # 回调处理
│   │   ├── message_sender.go # 消息发送消费者
│   │   └── transfer_executor.go # 转人工执行器
│   ├── model/               # 数据模型
│   │   └── message.go
│   ├── queue/               # 消息队列抽象
│   │   ├── queue.go         # 队列接口
│   │   └── redis.go         # Redis Streams 实现
│   └── service/             # 业务服务
│       ├── session.go        # 会话服务
│       ├── message.go        # 消息服务
│       └── transfer.go      # 转人工服务
├── configs/
│   └── config.yaml          # 配置文件
├── go.mod
├── Dockerfile
└── README.md
```

## 快速开始

### 1. 环境要求

- Go 1.21+
- MySQL 8.0+
- Redis 6.0+

### 2. 配置

复制并编辑配置文件：

```bash
cp configs/config.yaml configs/config.local.yaml
```

编辑 `configs/config.local.yaml`：

```yaml
server:
  addr: ":8080"
  mode: "debug"

database:
  dsn: "root:password@tcp(localhost:3306)/ai_cs?charset=utf8mb4&parseTime=True&loc=Local"

redis:
  addr: "localhost:6379"
  password: ""
  db: 0

queue:
  type: "redis"
  config:
    addr: "localhost:6379"
    password: ""
    db: 0
```

### 3. 安装依赖

```bash
go mod download
```

### 4. 运行

```bash
go run cmd/main.go
```

或编译后运行：

```bash
go build -o gateway cmd/main.go
./gateway
```

### 5. Docker 运行

```bash
docker build -t hzd-gateway .
docker run -p 8080:8080 -v $(pwd)/configs:/app/configs hzd-gateway
```

## API 接口

### 健康检查

```
GET /health
```

### 渠道回调

```
POST /api/callback/:channel/:app_id
```

**参数**:
- `channel`: 渠道类型（当前仅支持 `wecom`）
- `app_id`: 应用ID

**示例**:
```bash
curl -X POST http://localhost:8080/api/callback/wecom/app_001 \
  -H "Content-Type: application/json" \
  -d '{"ToUserName":"企业ID","FromUserName":"用户ID","CreateTime":1234567890,"MsgType":"text","Content":"你好","MsgId":"消息ID"}'
```

## 消息队列

网关使用以下队列：

- `incoming_messages`: 待处理消息队列（网关 → 处理器）
- `outgoing_messages`: 待发送消息队列（处理器 → 网关）
- `transfer_requests`: 转人工请求队列（处理器 → 网关）

## 企业微信集成

### 配置

在管理后台配置企业微信渠道，需要以下信息：

- `corp_id`: 企业ID
- `agent_id`: 应用ID
- `secret`: 应用Secret
- `token`: 回调Token
- `encoding_aes_key`: 加密Key

### 回调URL

配置企业微信回调URL为：

```
https://your-domain.com/api/callback/wecom/{app_id}
```

### 消息流程

1. 企业微信推送消息到回调URL
2. 网关验证签名
3. 解析消息并转换为统一格式
4. 创建/更新会话
5. 保存消息记录
6. 推入 `incoming_messages` 队列
7. 快速响应企业微信

## 开发指南

### 企业微信适配器实现说明

详细实现说明请参考：[wecom_notes.md](internal/adapter/wecom_notes.md)

### 添加新渠道适配器

1. 在 `internal/adapter/` 创建新适配器文件
2. 实现 `ChannelAdapter` 接口
3. 在 `adapter.go` 的 `NewAdapterFactory` 中注册

示例：

```go
// internal/adapter/newchannel.go
func NewNewChannelAdapter(config map[string]interface{}) (ChannelAdapter, error) {
    // 实现适配器
}

// internal/adapter/adapter.go
factory.Register("newchannel", NewNewChannelAdapter)
```

## 部署

### Docker Compose

参考项目根目录的 `docker-compose.yml`

### Kubernetes

参考项目文档中的 K8s 部署配置

## 日志

日志使用 Zap，支持结构化日志输出。在开发模式下输出到控制台，生产模式输出 JSON 格式。

## 许可证

内部项目
