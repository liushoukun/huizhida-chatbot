# 企业微信适配器实现说明

## 已实现功能

### 1. Access Token 管理
- ✅ 自动获取 access_token
- ✅ 本地缓存机制（使用 sync.RWMutex 保证线程安全）
- ✅ 自动刷新（提前 200 秒刷新，避免过期）
- ✅ Token 过期自动重试

### 2. 消息发送 (SendMessage)
- ✅ 调用企业微信发送消息 API
- ✅ 从 SessionID 解析用户ID
- ✅ 支持文本消息发送
- ✅ 错误处理和重试机制

### 3. 转人工功能
- ✅ TransferToQueue - 转接到客服队列
- ✅ TransferToSpecific - 转接到指定客服
- ✅ 发送转接提示消息

### 4. 签名验证
- ✅ VerifySignature - 验证企业微信回调签名

### 5. 消息解析
- ✅ ParseMessage - 解析企业微信消息格式

## 注意事项

### SessionID 格式
SessionID 必须遵循以下格式：
```
wecom_{userid}_{appid}
```

例如：`wecom_wxuser001_app001`

### 转人工实现说明

企业微信有两种转接方式：

1. **企业微信客服转接**（需要 open_kfid 和 external_userid）
   - 使用 API: `/cgi-bin/kf/servicer/transfer`
   - 需要从会话中获取 `open_kfid` 和 `external_userid`
   - 适用于企业微信客服场景

2. **企业微信应用消息转接**（当前实现）
   - 发送提示消息告知用户已转接
   - 标记会话状态为 `pending_human` 或 `transferred`
   - 需要配合人工客服系统实现消息路由
   - 适用于企业微信应用消息场景

当前实现使用的是方式2，如果需要使用方式1，需要：
1. 在会话创建时保存 `open_kfid` 和 `external_userid`
2. 修改 `TransferToQueue` 和 `TransferToSpecific` 方法
3. 调用客服转接 API

### 配置要求

企业微信适配器需要以下配置：

```json
{
  "corp_id": "企业ID",
  "agent_id": "应用ID",
  "secret": "应用Secret",
  "token": "回调Token",
  "encoding_aes_key": "加密Key"
}
```

### API 端点

- 获取 access_token: `GET https://qyapi.weixin.qq.com/cgi-bin/gettoken`
- 发送消息: `POST https://qyapi.weixin.qq.com/cgi-bin/message/send`
- 客服转接: `POST https://qyapi.weixin.qq.com/cgi-bin/kf/servicer/transfer`

### 错误码处理

- `40014`: access_token 无效
- `42001`: access_token 已过期

遇到这些错误码时，会自动清除缓存并重试一次。

## 待优化项

1. **从数据库获取用户信息**
   - 当前从 SessionID 解析用户ID
   - 可以改为从数据库查询会话获取完整的用户信息

2. **支持更多消息类型**
   - 当前只支持文本消息
   - 可以扩展支持图片、语音、视频等

3. **客服转接 API**
   - 如果需要使用企业微信客服转接
   - 需要实现完整的客服转接逻辑

4. **消息加密/解密**
   - 当前签名验证是简化版本
   - 如果需要处理加密消息，需要实现完整的加解密逻辑
