package handler

import (
	"context"

	"go.uber.org/zap"

	"github.com/huizhida/gateway/internal/adapter"
	"github.com/huizhida/gateway/internal/model"
	"github.com/huizhida/gateway/internal/queue"
	"github.com/huizhida/gateway/internal/service"
)

var (
	_ = queue.DeserializeMessage // 确保导入
)

// MessageSender 消息发送器（消费回复队列）
type MessageSender struct {
	adapterFactory *adapter.AdapterFactory
	messageService *service.MessageService
	mq             queue.MessageQueue
	logger         *zap.Logger
}

// NewMessageSender 创建消息发送器
func NewMessageSender(
	adapterFactory *adapter.AdapterFactory,
	messageService *service.MessageService,
	mq queue.MessageQueue,
	logger *zap.Logger,
) *MessageSender {
	return &MessageSender{
		adapterFactory: adapterFactory,
		messageService: messageService,
		mq:             mq,
		logger:         logger,
	}
}

// Start 启动消息发送消费者
func (m *MessageSender) Start(ctx context.Context) {
	msgChan, err := m.mq.Subscribe(ctx, "outgoing_messages")
	if err != nil {
		m.logger.Fatal("订阅回复队列失败", zap.Error(err))
		return
	}

	m.logger.Info("消息发送消费者已启动")

	for {
		select {
		case <-ctx.Done():
			m.logger.Info("消息发送消费者已停止")
			return
		case msg, ok := <-msgChan:
			if !ok {
				m.logger.Warn("消息通道已关闭")
				return
			}

			m.handleMessage(ctx, msg)
		}
	}
}

// handleMessage 处理单条消息
func (m *MessageSender) handleMessage(ctx context.Context, queueMsg queue.Message) {
	var outgoingMsg model.OutgoingMessage
	if err := queue.DeserializeMessage(queueMsg.Data, &outgoingMsg); err != nil {
		m.logger.Error("反序列化消息失败", zap.Error(err))
		m.mq.Nack(ctx, queueMsg)
		return
	}

	// 获取渠道适配器
	// TODO: 从数据库获取渠道配置
	channelConfig := make(map[string]interface{}) // 实际应从数据库读取
	adapter, err := m.adapterFactory.Get(outgoingMsg.Channel, channelConfig)
	if err != nil {
		m.logger.Error("获取适配器失败", zap.String("channel", outgoingMsg.Channel), zap.Error(err))
		m.mq.Nack(ctx, queueMsg)
		return
	}

	// 发送消息
	if err := adapter.SendMessage(&outgoingMsg); err != nil {
		m.logger.Error("发送消息失败", zap.Error(err))
		m.mq.Nack(ctx, queueMsg)
		return
	}

	// 更新消息状态
	if err := m.messageService.UpdateStatus(outgoingMsg.MessageID, "sent"); err != nil {
		m.logger.Warn("更新消息状态失败", zap.Error(err))
	}

	// 确认消费
	if err := m.mq.Ack(ctx, queueMsg); err != nil {
		m.logger.Warn("确认消息失败", zap.Error(err))
	}

	m.logger.Info("消息发送成功",
		zap.String("message_id", outgoingMsg.MessageID),
		zap.String("session_id", outgoingMsg.SessionID),
		zap.String("channel", outgoingMsg.Channel),
	)
}
