package service

import (
	"encoding/json"
	"time"

	"go.uber.org/zap"
	"gorm.io/gorm"

	"github.com/huizhida/gateway/internal/model"
)

// MessageService 消息服务
type MessageService struct {
	db     *gorm.DB
	logger *zap.Logger
}

// NewMessageService 创建消息服务
func NewMessageService(db *gorm.DB, logger *zap.Logger) *MessageService {
	return &MessageService{
		db:     db,
		logger: logger,
	}
}

// Save 保存消息
func (m *MessageService) Save(msg *model.UnifiedMessage) error {
	contentData, _ := json.Marshal(msg.Content)

	message := model.Message{
		MessageID:        msg.MessageID,
		SessionID:        msg.SessionID,
		AppID:            msg.AppID,
		Channel:          msg.Channel,
		Direction:        1, // 接收
		MessageType:      msg.MessageType,
		Content:          contentData,
		ChannelMessageID: msg.ChannelMessageID,
		SenderType:       "user",
		Status:           "pending",
		CreatedAt:        time.Now(),
	}

	if err := m.db.Create(&message).Error; err != nil {
		m.logger.Error("保存消息失败", zap.Error(err))
		return err
	}

	return nil
}

// UpdateStatus 更新消息状态
func (m *MessageService) UpdateStatus(messageID string, status string) error {
	return m.db.Model(&model.Message{}).Where("message_id = ?", messageID).Update("status", status).Error
}

// SaveOutgoing 保存发送的消息
func (m *MessageService) SaveOutgoing(msg *model.OutgoingMessage, sessionID string, channel string, appID string) error {
	contentData, _ := json.Marshal(map[string]interface{}{
		"text": msg.Reply,
	})

	message := model.Message{
		MessageID:   msg.MessageID,
		SessionID:   sessionID,
		AppID:       appID,
		Channel:     channel,
		Direction:   2, // 发送
		MessageType: msg.ReplyType,
		Content:     contentData,
		SenderType:  "agent",
		Status:      "sent",
		CreatedAt:   time.Now(),
	}

	if err := m.db.Create(&message).Error; err != nil {
		m.logger.Error("保存发送消息失败", zap.Error(err))
		return err
	}

	return nil
}
