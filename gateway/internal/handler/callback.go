package handler

import (
	"io"
	"net/http"

	"github.com/gin-gonic/gin"
	"go.uber.org/zap"

	"github.com/huizhida/gateway/internal/adapter"
	"github.com/huizhida/gateway/internal/model"
	"github.com/huizhida/gateway/internal/queue"
	"github.com/huizhida/gateway/internal/service"
)

// CallbackHandler 回调处理器
type CallbackHandler struct {
	adapterFactory *adapter.AdapterFactory
	sessionService *service.SessionService
	messageService *service.MessageService
	mq             queue.MessageQueue
	logger         *zap.Logger
}

// NewCallbackHandler 创建回调处理器
func NewCallbackHandler(
	adapterFactory *adapter.AdapterFactory,
	sessionService *service.SessionService,
	messageService *service.MessageService,
	mq queue.MessageQueue,
	logger *zap.Logger,
) *CallbackHandler {
	return &CallbackHandler{
		adapterFactory: adapterFactory,
		sessionService: sessionService,
		messageService: messageService,
		mq:             mq,
		logger:         logger,
	}
}

// HandleCallback 处理渠道回调
func (h *CallbackHandler) HandleCallback(c *gin.Context) {
	channel := c.Param("channel")
	appID := c.Param("app_id")

	// 1. 获取渠道适配器
	// TODO: 从数据库获取渠道配置
	channelConfig := make(map[string]interface{}) // 实际应从数据库读取
	adapter, err := h.adapterFactory.Get(channel, channelConfig)
	if err != nil {
		h.logger.Error("获取适配器失败", zap.String("channel", channel), zap.Error(err))
		c.JSON(400, gin.H{"error": "unsupported channel"})
		return
	}

	// 2. 验证签名
	if !adapter.VerifySignature(c.Request) {
		h.logger.Warn("签名验证失败", zap.String("channel", channel))
		c.JSON(403, gin.H{"error": "invalid signature"})
		return
	}

	// 3. 读取请求体
	rawData, err := io.ReadAll(c.Request.Body)
	if err != nil {
		h.logger.Error("读取请求体失败", zap.Error(err))
		c.JSON(400, gin.H{"error": "read body failed"})
		return
	}

	// 4. 解析并转换消息
	message, err := adapter.ParseMessage(rawData)
	if err != nil {
		h.logger.Error("解析消息失败", zap.Error(err))
		c.JSON(400, gin.H{"error": "parse message failed"})
		return
	}

	message.AppID = appID
	message.Channel = channel

	// 5. 获取或创建会话
	session, err := h.sessionService.GetOrCreate(message)
	if err != nil {
		h.logger.Error("获取或创建会话失败", zap.Error(err))
		c.JSON(500, gin.H{"error": "session error"})
		return
	}
	message.SessionID = session.SessionID

	// 6. 保存消息记录
	if err := h.messageService.Save(message); err != nil {
		h.logger.Error("保存消息失败", zap.Error(err))
		// 继续处理，不返回错误
	}

	// 7. 推入待处理队列
	if err := h.mq.Publish(c.Request.Context(), "incoming_messages", message); err != nil {
		h.logger.Error("消息入队失败", zap.Error(err))
		c.JSON(500, gin.H{"error": "queue error"})
		return
	}

	// 8. 快速响应渠道
	response := adapter.GetSuccessResponse()
	c.JSON(200, response)

	h.logger.Info("回调处理成功",
		zap.String("channel", channel),
		zap.String("app_id", appID),
		zap.String("session_id", session.SessionID),
		zap.String("message_id", message.MessageID),
	)
}
