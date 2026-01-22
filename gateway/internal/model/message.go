package model

import (
	"encoding/json"
	"time"
)

// UnifiedMessage 统一消息格式
type UnifiedMessage struct {
	MessageID         string                 `json:"message_id"`
	AppID             string                 `json:"app_id"`
	Channel           string                 `json:"channel"`
	ChannelMessageID  string                 `json:"channel_message_id"`
	SessionID         string                 `json:"session_id"`
	User              UserInfo               `json:"user"`
	MessageType       string                 `json:"message_type"` // text, image, voice, video, file, link, location, event
	Content           MessageContent         `json:"content"`
	Timestamp         int64                  `json:"timestamp"`
	RawData           json.RawMessage        `json:"raw_data,omitempty"`
}

// UserInfo 用户信息
type UserInfo struct {
	ChannelUserID string   `json:"channel_user_id"`
	Nickname      string   `json:"nickname"`
	Avatar        string   `json:"avatar"`
	IsVIP         bool     `json:"is_vip"`
	Tags          []string `json:"tags"`
}

// MessageContent 消息内容
type MessageContent struct {
	Text      string                 `json:"text,omitempty"`
	MediaURL  string                 `json:"media_url,omitempty"`
	MediaType string                 `json:"media_type,omitempty"`
	Extra     map[string]interface{} `json:"extra,omitempty"`
}

// OutgoingMessage 待发送消息
type OutgoingMessage struct {
	MessageID  string `json:"message_id"`
	SessionID  string `json:"session_id"`
	Channel    string `json:"channel"`
	Reply      string `json:"reply"`
	ReplyType  string `json:"reply_type"` // text, rich
	RichContent map[string]interface{} `json:"rich_content,omitempty"`
}

// TransferRequest 转人工请求
type TransferRequest struct {
	SessionID      string `json:"session_id"`
	Channel        string `json:"channel"`
	Reason         string `json:"reason"`
	Source         string `json:"source"` // rule, agent
	AgentReason    string `json:"agent_reason,omitempty"`
	Priority       string `json:"priority"` // high, normal, low
	Mode           string `json:"mode,omitempty"` // queue, specific
	SpecificServicer string `json:"specific_servicer,omitempty"`
	Context        map[string]interface{} `json:"context,omitempty"`
	Timestamp      string `json:"timestamp"`
}

// Session 会话信息
type Session struct {
	ID              uint64                 `gorm:"primaryKey" json:"id"`
	SessionID      string                 `gorm:"uniqueIndex;size:64" json:"session_id"`
	AppID          string                 `gorm:"index;size:32" json:"app_id"`
	Channel        string                 `gorm:"index;size:20" json:"channel"`
	ChannelUserID  string                 `gorm:"index;size:64" json:"channel_user_id"`
	UserNickname   string                 `gorm:"size:100" json:"user_nickname"`
	IsVIP          bool                   `json:"is_vip"`
	Status         string                 `gorm:"size:20" json:"status"` // active, pending_agent, pending_human, transferred, closed
	CurrentAgentID *uint64                `json:"current_agent_id"`
	Context        json.RawMessage        `gorm:"type:json" json:"context"`
	TransferReason string                 `gorm:"size:100" json:"transfer_reason"`
	TransferSource string                 `gorm:"size:20" json:"transfer_source"` // rule, agent
	TransferTime   *time.Time             `json:"transfer_time"`
	AssignedHuman  string                 `gorm:"size:64" json:"assigned_human"`
	CreatedAt      time.Time              `json:"created_at"`
	UpdatedAt      time.Time              `json:"updated_at"`
	ClosedAt       *time.Time             `json:"closed_at"`
}

// Message 消息记录
type Message struct {
	ID                  uint64      `gorm:"primaryKey" json:"id"`
	MessageID           string      `gorm:"uniqueIndex;size:64" json:"message_id"`
	SessionID           string      `gorm:"index;size:64" json:"session_id"`
	AppID               string      `gorm:"index;size:32" json:"app_id"`
	Channel             string      `gorm:"index;size:20" json:"channel"`
	Direction           int         `json:"direction"` // 1: 接收, 2: 发送
	MessageType         string      `gorm:"size:20" json:"message_type"`
	Content             json.RawMessage `gorm:"type:json" json:"content"`
	ChannelMessageID    string      `gorm:"size:64" json:"channel_message_id"`
	SenderType          string      `gorm:"size:20" json:"sender_type"` // user, agent, human
	ProcessedByAgentID  *uint64     `json:"processed_by_agent_id"`
	Status              string      `gorm:"size:20" json:"status"` // pending, sent, failed
	CreatedAt           time.Time   `json:"created_at"`
}
