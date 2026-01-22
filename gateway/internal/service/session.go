package service

import (
	"encoding/json"
	"time"

	"go.uber.org/zap"
	"gorm.io/gorm"

	"github.com/huizhida/gateway/internal/model"
)

// SessionService 会话服务
type SessionService struct {
	db     *gorm.DB
	logger *zap.Logger
}

// NewSessionService 创建会话服务
func NewSessionService(db *gorm.DB, logger *zap.Logger) *SessionService {
	return &SessionService{
		db:     db,
		logger: logger,
	}
}

// GetOrCreate 获取或创建会话
func (s *SessionService) GetOrCreate(msg *model.UnifiedMessage) (*model.Session, error) {
	// 生成会话ID：channel + channel_user_id + app_id
	sessionID := generateSessionID(msg.Channel, msg.User.ChannelUserID, msg.AppID)

	var session model.Session
	err := s.db.Where("session_id = ?", sessionID).First(&session).Error

	if err == gorm.ErrRecordNotFound {
		// 创建新会话
		contextData, _ := json.Marshal(map[string]interface{}{
			"history": []interface{}{},
			"variables": map[string]interface{}{},
		})

		session = model.Session{
			SessionID:     sessionID,
			AppID:         msg.AppID,
			Channel:       msg.Channel,
			ChannelUserID: msg.User.ChannelUserID,
			UserNickname:  msg.User.Nickname,
			IsVIP:         msg.User.IsVIP,
			Status:        "active",
			Context:       contextData,
			CreatedAt:     time.Now(),
			UpdatedAt:     time.Now(),
		}

		if err := s.db.Create(&session).Error; err != nil {
			s.logger.Error("创建会话失败", zap.Error(err))
			return nil, err
		}

		s.logger.Info("创建新会话", zap.String("session_id", sessionID))
	} else if err != nil {
		s.logger.Error("查询会话失败", zap.Error(err))
		return nil, err
	} else {
		// 更新会话时间
		session.UpdatedAt = time.Now()
		s.db.Save(&session)
	}

	return &session, nil
}

// Get 获取会话
func (s *SessionService) Get(sessionID string) (*model.Session, error) {
	var session model.Session
	err := s.db.Where("session_id = ?", sessionID).First(&session).Error
	if err != nil {
		return nil, err
	}
	return &session, nil
}

// Update 更新会话
func (s *SessionService) Update(sessionID string, updates map[string]interface{}) error {
	updates["updated_at"] = time.Now()
	return s.db.Model(&model.Session{}).Where("session_id = ?", sessionID).Updates(updates).Error
}

// UpdateStatus 更新会话状态
func (s *SessionService) UpdateStatus(sessionID string, status string, reason string, source string) error {
	updates := map[string]interface{}{
		"status":          status,
		"transfer_reason": reason,
		"transfer_source": source,
		"updated_at":      time.Now(),
	}

	if status == "pending_human" || status == "transferred" {
		now := time.Now()
		updates["transfer_time"] = &now
	}

	return s.db.Model(&model.Session{}).Where("session_id = ?", sessionID).Updates(updates).Error
}

// generateSessionID 生成会话ID
func generateSessionID(channel, channelUserID, appID string) string {
	// 使用简单的组合方式，实际可以使用更复杂的算法
	return channel + "_" + channelUserID + "_" + appID
}
