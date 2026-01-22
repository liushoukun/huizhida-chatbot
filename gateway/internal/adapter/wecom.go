package adapter

import (
	"bytes"
	"crypto/sha1"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"sort"
	"strings"
	"sync"
	"time"

	"github.com/google/uuid"
	"github.com/huizhida/gateway/internal/model"
)

const (
	wecomAPIBaseURL = "https://qyapi.weixin.qq.com"
	accessTokenTTL  = 7000 // access_token 有效期 7200 秒，提前 200 秒刷新
)

// WecomAdapter 企业微信适配器
type WecomAdapter struct {
	config      WecomConfig
	httpClient  *http.Client
	accessToken string
	tokenExpire time.Time
	tokenMutex  sync.RWMutex
}

// WecomConfig 企业微信配置
type WecomConfig struct {
	CorpID        string `json:"corp_id"`
	AgentID       string `json:"agent_id"`
	Secret        string `json:"secret"`
	Token         string `json:"token"`
	EncodingAESKey string `json:"encoding_aes_key"`
}

// NewWecomAdapter 创建企业微信适配器
func NewWecomAdapter(config map[string]interface{}) (ChannelAdapter, error) {
	cfg := WecomConfig{}
	if err := unmarshalConfig(config, &cfg); err != nil {
		return nil, err
	}

	// 验证必要配置
	if cfg.CorpID == "" || cfg.Secret == "" || cfg.AgentID == "" {
		return nil, fmt.Errorf("企业微信配置不完整: corp_id, secret, agent_id 为必填项")
	}

	return &WecomAdapter{
		config:     cfg,
		httpClient: &http.Client{Timeout: 10 * time.Second},
	}, nil
}

// VerifySignature 验证企业微信签名
func (a *WecomAdapter) VerifySignature(r *http.Request) bool {
	// 企业微信签名验证逻辑
	// 1. 获取参数
	timestamp := r.URL.Query().Get("timestamp")
	nonce := r.URL.Query().Get("nonce")
	signature := r.URL.Query().Get("msg_signature")

	if timestamp == "" || nonce == "" || signature == "" {
		return false
	}

	// 2. 读取请求体
	body, err := io.ReadAll(r.Body)
	if err != nil {
		return false
	}
	r.Body = io.NopCloser(bytes.NewReader(body))

	// 3. 计算签名
	// 企业微信签名算法：对 token、timestamp、nonce、加密消息体进行字典序排序后拼接，然后进行 SHA1 加密
	params := []string{a.config.Token, timestamp, nonce, string(body)}
	sort.Strings(params)
	s := strings.Join(params, "")
	hash := sha1.Sum([]byte(s))
	expectedSignature := fmt.Sprintf("%x", hash)

	return expectedSignature == signature
}

// ParseMessage 解析企业微信消息
func (a *WecomAdapter) ParseMessage(rawData []byte) (*model.UnifiedMessage, error) {
	var wecomMsg WecomMessage
	if err := json.Unmarshal(rawData, &wecomMsg); err != nil {
		return nil, fmt.Errorf("%w: %v", ErrParseMessage, err)
	}

	// 转换为统一格式
	msg := &model.UnifiedMessage{
		MessageID:        uuid.New().String(),
		ChannelMessageID: wecomMsg.MsgID,
		MessageType:      mapWecomMessageType(wecomMsg.MsgType),
		Timestamp:         time.Now().UnixMilli(),
		RawData:           rawData,
		User: model.UserInfo{
			ChannelUserID: wecomMsg.FromUserName,
			Nickname:      wecomMsg.FromUserName, // 企业微信需要额外获取用户信息
		},
		Content: model.MessageContent{
			Text: wecomMsg.Content,
		},
	}

	return msg, nil
}

// ConvertToChannelFormat 转换为企业微信格式
func (a *WecomAdapter) ConvertToChannelFormat(msg *model.OutgoingMessage) interface{} {
	return map[string]interface{}{
		"touser":  "", // 需要从会话中获取
		"msgtype": "text",
		"agentid": a.config.AgentID,
		"text": map[string]string{
			"content": msg.Reply,
		},
	}
}

// getAccessToken 获取 access_token（带缓存）
func (a *WecomAdapter) getAccessToken() (string, error) {
	a.tokenMutex.RLock()
	// 检查缓存是否有效
	if a.accessToken != "" && time.Now().Before(a.tokenExpire) {
		token := a.accessToken
		a.tokenMutex.RUnlock()
		return token, nil
	}
	a.tokenMutex.RUnlock()

	// 获取新的 access_token
	a.tokenMutex.Lock()
	defer a.tokenMutex.Unlock()

	// 双重检查
	if a.accessToken != "" && time.Now().Before(a.tokenExpire) {
		return a.accessToken, nil
	}

	// 调用企业微信 API 获取 access_token
	url := fmt.Sprintf("%s/cgi-bin/gettoken?corpid=%s&corpsecret=%s",
		wecomAPIBaseURL, a.config.CorpID, a.config.Secret)

	resp, err := a.httpClient.Get(url)
	if err != nil {
		return "", fmt.Errorf("获取 access_token 失败: %w", err)
	}
	defer resp.Body.Close()

	var result struct {
		ErrCode     int    `json:"errcode"`
		ErrMsg      string `json:"errmsg"`
		AccessToken string `json:"access_token"`
		ExpiresIn   int    `json:"expires_in"`
	}

	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return "", fmt.Errorf("解析 access_token 响应失败: %w", err)
	}

	if result.ErrCode != 0 {
		return "", fmt.Errorf("获取 access_token 失败: %s (errcode: %d)", result.ErrMsg, result.ErrCode)
	}

	// 缓存 access_token
	a.accessToken = result.AccessToken
	a.tokenExpire = time.Now().Add(time.Duration(result.ExpiresIn-200) * time.Second)

	return a.accessToken, nil
}

// SendMessage 发送消息到企业微信
// 注意：msg.SessionID 应该包含用户ID信息，格式为: wecom_{userid}_{appid}
func (a *WecomAdapter) SendMessage(msg *model.OutgoingMessage) error {
	// 1. 获取 access_token
	accessToken, err := a.getAccessToken()
	if err != nil {
		return fmt.Errorf("%w: %v", ErrSendMessage, err)
	}

	// 2. 从 SessionID 解析用户ID
	// SessionID 格式: wecom_{userid}_{appid}
	parts := strings.Split(msg.SessionID, "_")
	if len(parts) < 2 || parts[0] != "wecom" {
		return fmt.Errorf("%w: 无效的 SessionID 格式，无法解析用户ID", ErrSendMessage)
	}
	userID := parts[1]

	// 3. 构建发送消息请求
	sendReq := map[string]interface{}{
		"touser":  userID,
		"msgtype": "text",
		"agentid": a.config.AgentID,
		"text": map[string]string{
			"content": msg.Reply,
		},
		"safe": 0, // 是否保密消息，0-否，1-是
	}

	reqBody, err := json.Marshal(sendReq)
	if err != nil {
		return fmt.Errorf("%w: 序列化请求失败: %v", ErrSendMessage, err)
	}

	// 3. 调用企业微信发送消息 API
	url := fmt.Sprintf("%s/cgi-bin/message/send?access_token=%s", wecomAPIBaseURL, accessToken)
	resp, err := a.httpClient.Post(url, "application/json", bytes.NewReader(reqBody))
	if err != nil {
		return fmt.Errorf("%w: 请求失败: %v", ErrSendMessage, err)
	}
	defer resp.Body.Close()

	var result struct {
		ErrCode      int    `json:"errcode"`
		ErrMsg       string `json:"errmsg"`
		InvalidUser  string `json:"invaliduser,omitempty"`
		InvalidParty string `json:"invalidparty,omitempty"`
		InvalidTag   string `json:"invalidtag,omitempty"`
	}

	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return fmt.Errorf("%w: 解析响应失败: %v", ErrSendMessage, err)
	}

	if result.ErrCode != 0 {
		// access_token 过期，清除缓存重试一次
		if result.ErrCode == 40014 || result.ErrCode == 42001 {
			a.tokenMutex.Lock()
			a.accessToken = ""
			a.tokenMutex.Unlock()
			// 重试一次
			return a.SendMessage(msg)
		}
		return fmt.Errorf("%w: %s (errcode: %d)", ErrSendMessage, result.ErrMsg, result.ErrCode)
	}

	return nil
}

// TransferToQueue 转接到客服队列
// sessionID 格式: wecom_{userid}_{appid}
// 注意：企业微信客服转接需要 open_kfid 和 external_userid，这些信息需要从会话上下文或数据库获取
// 如果使用企业微信应用消息（非客服），则使用不同的转接方式
func (a *WecomAdapter) TransferToQueue(sessionID string, priority string) error {
	// 解析 sessionID 获取用户ID
	parts := strings.Split(sessionID, "_")
	if len(parts) < 2 || parts[0] != "wecom" {
		return fmt.Errorf("%w: 无效的 SessionID 格式", ErrTransferFailed)
	}
	userID := parts[1]

	// 企业微信有两种转接方式：
	// 1. 企业微信客服（需要 open_kfid 和 external_userid）
	// 2. 企业微信应用消息（使用应用转接）

	// 这里实现应用消息转接方式（更通用）
	// 如果需要使用客服转接，需要从会话中获取 open_kfid 和 external_userid
	// 暂时返回成功，实际转接逻辑需要根据业务场景实现
	// 可以通过发送提示消息告知用户已转接

	// 1. 获取 access_token
	accessToken, err := a.getAccessToken()
	if err != nil {
		return fmt.Errorf("%w: %v", ErrTransferFailed, err)
	}

	// 2. 发送转接提示消息
	transferMsg := map[string]interface{}{
		"touser":  userID,
		"msgtype": "text",
		"agentid": a.config.AgentID,
		"text": map[string]string{
			"content": "正在为您转接人工客服，请稍候...",
		},
		"safe": 0,
	}

	reqBody, err := json.Marshal(transferMsg)
	if err != nil {
		return fmt.Errorf("%w: 序列化请求失败: %v", ErrTransferFailed, err)
	}

	url := fmt.Sprintf("%s/cgi-bin/message/send?access_token=%s", wecomAPIBaseURL, accessToken)
	resp, err := a.httpClient.Post(url, "application/json", bytes.NewReader(reqBody))
	if err != nil {
		return fmt.Errorf("%w: 请求失败: %v", ErrTransferFailed, err)
	}
	defer resp.Body.Close()

	var result struct {
		ErrCode int    `json:"errcode"`
		ErrMsg  string `json:"errmsg"`
	}

	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return fmt.Errorf("%w: 解析响应失败: %v", ErrTransferFailed, err)
	}

	if result.ErrCode != 0 {
		if result.ErrCode == 40014 || result.ErrCode == 42001 {
			a.tokenMutex.Lock()
			a.accessToken = ""
			a.tokenMutex.Unlock()
			return a.TransferToQueue(sessionID, priority)
		}
		return fmt.Errorf("%w: %s (errcode: %d)", ErrTransferFailed, result.ErrMsg, result.ErrCode)
	}

	// 注意：企业微信应用消息转接需要配合其他系统实现
	// 这里只是发送了提示消息，实际的转接逻辑需要：
	// 1. 标记会话状态为 pending_human
	// 2. 通知人工客服系统
	// 3. 后续消息路由到人工客服

	return nil

	reqBody, err := json.Marshal(transferReq)
	if err != nil {
		return fmt.Errorf("%w: 序列化请求失败: %v", ErrTransferFailed, err)
	}

	// 3. 调用转接 API
	url := fmt.Sprintf("%s/cgi-bin/kf/servicer/transfer?access_token=%s", wecomAPIBaseURL, accessToken)
	resp, err := a.httpClient.Post(url, "application/json", bytes.NewReader(reqBody))
	if err != nil {
		return fmt.Errorf("%w: 请求失败: %v", ErrTransferFailed, err)
	}
	defer resp.Body.Close()

	var result struct {
		ErrCode int    `json:"errcode"`
		ErrMsg  string `json:"errmsg"`
	}

	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return fmt.Errorf("%w: 解析响应失败: %v", ErrTransferFailed, err)
	}

	if result.ErrCode != 0 {
		// access_token 过期，清除缓存重试一次
		if result.ErrCode == 40014 || result.ErrCode == 42001 {
			a.tokenMutex.Lock()
			a.accessToken = ""
			a.tokenMutex.Unlock()
			return a.TransferToQueue(sessionID, priority)
		}
		return fmt.Errorf("%w: %s (errcode: %d)", ErrTransferFailed, result.ErrMsg, result.ErrCode)
	}

	return nil
}

// TransferToSpecific 转接到指定客服
// sessionID 格式: wecom_{userid}_{appid}
// servicerID: 指定的客服用户ID
func (a *WecomAdapter) TransferToSpecific(sessionID string, servicerID string, priority string) error {
	// 解析 sessionID 获取用户ID
	parts := strings.Split(sessionID, "_")
	if len(parts) < 2 || parts[0] != "wecom" {
		return fmt.Errorf("%w: 无效的 SessionID 格式", ErrTransferFailed)
	}
	userID := parts[1]

	// 1. 获取 access_token
	accessToken, err := a.getAccessToken()
	if err != nil {
		return fmt.Errorf("%w: %v", ErrTransferFailed, err)
	}

	// 2. 发送转接提示消息
	transferMsg := map[string]interface{}{
		"touser":  userID,
		"msgtype": "text",
		"agentid": a.config.AgentID,
		"text": map[string]string{
			"content": fmt.Sprintf("正在为您转接到客服 %s，请稍候...", servicerID),
		},
		"safe": 0,
	}

	reqBody, err := json.Marshal(transferMsg)
	if err != nil {
		return fmt.Errorf("%w: 序列化请求失败: %v", ErrTransferFailed, err)
	}

	url := fmt.Sprintf("%s/cgi-bin/message/send?access_token=%s", wecomAPIBaseURL, accessToken)
	resp, err := a.httpClient.Post(url, "application/json", bytes.NewReader(reqBody))
	if err != nil {
		return fmt.Errorf("%w: 请求失败: %v", ErrTransferFailed, err)
	}
	defer resp.Body.Close()

	var result struct {
		ErrCode int    `json:"errcode"`
		ErrMsg  string `json:"errmsg"`
	}

	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return fmt.Errorf("%w: 解析响应失败: %v", ErrTransferFailed, err)
	}

	if result.ErrCode != 0 {
		if result.ErrCode == 40014 || result.ErrCode == 42001 {
			a.tokenMutex.Lock()
			a.accessToken = ""
			a.tokenMutex.Unlock()
			return a.TransferToSpecific(sessionID, servicerID, priority)
		}
		return fmt.Errorf("%w: %s (errcode: %d)", ErrTransferFailed, result.ErrMsg, result.ErrCode)
	}

	// 注意：企业微信应用消息转接到指定客服需要配合其他系统实现
	// 这里只是发送了提示消息，实际的转接逻辑需要：
	// 1. 标记会话状态为 transferred，并记录 assigned_human
	// 2. 通知指定的人工客服
	// 3. 后续消息路由到指定客服

	return nil

	reqBody, err := json.Marshal(transferReq)
	if err != nil {
		return fmt.Errorf("%w: 序列化请求失败: %v", ErrTransferFailed, err)
	}

	// 3. 调用转接 API
	url := fmt.Sprintf("%s/cgi-bin/kf/servicer/transfer?access_token=%s", wecomAPIBaseURL, accessToken)
	resp, err := a.httpClient.Post(url, "application/json", bytes.NewReader(reqBody))
	if err != nil {
		return fmt.Errorf("%w: 请求失败: %v", ErrTransferFailed, err)
	}
	defer resp.Body.Close()

	var result struct {
		ErrCode int    `json:"errcode"`
		ErrMsg  string `json:"errmsg"`
	}

	if err := json.NewDecoder(resp.Body).Decode(&result); err != nil {
		return fmt.Errorf("%w: 解析响应失败: %v", ErrTransferFailed, err)
	}

	if result.ErrCode != 0 {
		// access_token 过期，清除缓存重试一次
		if result.ErrCode == 40014 || result.ErrCode == 42001 {
			a.tokenMutex.Lock()
			a.accessToken = ""
			a.tokenMutex.Unlock()
			return a.TransferToSpecific(sessionID, servicerID, priority)
		}
		return fmt.Errorf("%w: %s (errcode: %d)", ErrTransferFailed, result.ErrMsg, result.ErrCode)
	}

	return nil
}

// GetSuccessResponse 获取成功响应
func (a *WecomAdapter) GetSuccessResponse() interface{} {
	return map[string]string{
		"errcode": "0",
		"errmsg":  "ok",
	}
}

// WecomMessage 企业微信消息格式
type WecomMessage struct {
	ToUserName   string `json:"ToUserName"`
	FromUserName string `json:"FromUserName"`
	CreateTime   int64  `json:"CreateTime"`
	MsgType      string `json:"MsgType"`
	Content      string `json:"Content"`
	MsgID        string `json:"MsgId"`
}

// mapWecomMessageType 映射企业微信消息类型
func mapWecomMessageType(wecomType string) string {
	mapping := map[string]string{
		"text":     "text",
		"image":    "image",
		"voice":    "voice",
		"video":    "video",
		"file":     "file",
		"link":     "link",
		"location": "location",
		"event":    "event",
	}

	if msgType, ok := mapping[wecomType]; ok {
		return msgType
	}
	return "text"
}

// unmarshalConfig 解析配置
func unmarshalConfig(config map[string]interface{}, target interface{}) error {
	data, err := json.Marshal(config)
	if err != nil {
		return err
	}
	return json.Unmarshal(data, target)
}
