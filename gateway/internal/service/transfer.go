package service

import (
	"go.uber.org/zap"

	"github.com/huizhida/gateway/internal/adapter"
	"github.com/huizhida/gateway/internal/model"
)

// TransferService 转人工服务
type TransferService struct {
	adapterFactory *adapter.AdapterFactory
	sessionService *SessionService
	messageService *MessageService
	logger         *zap.Logger
}

// NewTransferService 创建转人工服务
func NewTransferService(
	adapterFactory *adapter.AdapterFactory,
	sessionService *SessionService,
	messageService *MessageService,
	logger *zap.Logger,
) *TransferService {
	return &TransferService{
		adapterFactory: adapterFactory,
		sessionService: sessionService,
		messageService: messageService,
		logger:         logger,
	}
}

// Execute 执行转人工操作
func (t *TransferService) Execute(sessionID string, req *model.TransferRequest) error {
	// 1. 获取会话信息
	session, err := t.sessionService.Get(sessionID)
	if err != nil {
		t.logger.Error("获取会话失败", zap.Error(err))
		return err
	}

	// 2. 获取渠道适配器
	// TODO: 从数据库获取渠道配置
	channelConfig := make(map[string]interface{}) // 实际应从数据库读取
	adapter, err := t.adapterFactory.Get(session.Channel, channelConfig)
	if err != nil {
		t.logger.Error("获取适配器失败", zap.Error(err))
		return err
	}

	// 3. 发送提示消息
	tipMsg := &model.OutgoingMessage{
		MessageID: "tip_" + sessionID,
		SessionID: sessionID,
		Channel:   session.Channel,
		Reply:     "正在为您转接人工客服，请稍候...",
		ReplyType: "text",
	}
	if err := adapter.SendMessage(tipMsg); err != nil {
		t.logger.Warn("发送提示消息失败", zap.Error(err))
	}

	// 4. 调用渠道转人工API
	if req.Mode == "specific" && req.SpecificServicer != "" {
		err = adapter.TransferToSpecific(sessionID, req.SpecificServicer, req.Priority)
	} else {
		err = adapter.TransferToQueue(sessionID, req.Priority)
	}

	if err != nil {
		t.logger.Error("转人工失败", zap.Error(err))
		return err
	}

	// 5. 更新会话状态
	status := "pending_human"
	if req.Mode == "specific" {
		status = "transferred"
	}

	if err := t.sessionService.UpdateStatus(sessionID, status, req.Reason, req.Source); err != nil {
		t.logger.Error("更新会话状态失败", zap.Error(err))
		return err
	}

	t.logger.Info("转人工成功", zap.String("session_id", sessionID), zap.String("reason", req.Reason))
	return nil
}
