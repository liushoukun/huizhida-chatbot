package adapter

import (
	"io"
	"net/http"

	"github.com/huizhida/gateway/internal/model"
)

// ChannelAdapter 渠道适配器接口
type ChannelAdapter interface {
	// VerifySignature 验证签名
	VerifySignature(r *http.Request) bool

	// ParseMessage 解析渠道消息格式，转换为统一格式
	ParseMessage(rawData []byte) (*model.UnifiedMessage, error)

	// ConvertToChannelFormat 将统一格式转换为渠道格式
	ConvertToChannelFormat(msg *model.OutgoingMessage) interface{}

	// SendMessage 发送消息到渠道
	SendMessage(msg *model.OutgoingMessage) error

	// TransferToQueue 转接到客服队列
	TransferToQueue(sessionID string, priority string) error

	// TransferToSpecific 转接到指定客服
	TransferToSpecific(sessionID string, servicerID string, priority string) error

	// GetSuccessResponse 获取成功响应
	GetSuccessResponse() interface{}
}

// AdapterFactory 适配器工厂
type AdapterFactory struct {
	adapters map[string]func(config map[string]interface{}) (ChannelAdapter, error)
}

// NewAdapterFactory 创建适配器工厂
func NewAdapterFactory() *AdapterFactory {
	factory := &AdapterFactory{
		adapters: make(map[string]func(config map[string]interface{}) (ChannelAdapter, error)),
	}

	// 注册适配器
	factory.Register("wecom", NewWecomAdapter)

	return factory
}

// Register 注册适配器
func (f *AdapterFactory) Register(channel string, factory func(config map[string]interface{}) (ChannelAdapter, error)) {
	f.adapters[channel] = factory
}

// Get 获取适配器实例
func (f *AdapterFactory) Get(channel string, config map[string]interface{}) (ChannelAdapter, error) {
	factory, ok := f.adapters[channel]
	if !ok {
		return nil, ErrUnsupportedChannel
	}

	return factory(config)
}

// GetSupportedChannels 获取支持的渠道列表
func (f *AdapterFactory) GetSupportedChannels() []string {
	channels := make([]string, 0, len(f.adapters))
	for channel := range f.adapters {
		channels = append(channels, channel)
	}
	return channels
}
