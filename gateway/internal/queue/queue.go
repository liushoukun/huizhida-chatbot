package queue

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
)

// MessageQueue 消息队列接口
type MessageQueue interface {
	// Publish 发布消息到队列
	Publish(ctx context.Context, queue string, message interface{}) error

	// Subscribe 订阅队列消息
	Subscribe(ctx context.Context, queue string) (<-chan Message, error)

	// Ack 确认消息消费
	Ack(ctx context.Context, msg Message) error

	// Nack 拒绝消息，重新入队
	Nack(ctx context.Context, msg Message) error

	// Close 关闭队列连接
	Close() error
}

// Message 队列消息
type Message struct {
	ID      string
	Queue   string
	Data    []byte
	Headers map[string]string
}

// NewQueue 创建消息队列实例
func NewQueue(queueType string, config map[string]interface{}) (MessageQueue, error) {
	switch queueType {
	case "redis":
		return NewRedisQueue(config)
	case "rabbitmq":
		return NewRabbitMQ(config)
	case "kafka":
		return NewKafkaQueue(config)
	default:
		return nil, fmt.Errorf("不支持的消息队列类型: %s", queueType)
	}
}

// SerializeMessage 序列化消息
func SerializeMessage(msg interface{}) ([]byte, error) {
	return json.Marshal(msg)
}

// DeserializeMessage 反序列化消息
func DeserializeMessage(data []byte, target interface{}) error {
	return json.Unmarshal(data, target)
}

var (
	ErrQueueNotConnected = errors.New("队列未连接")
	ErrPublishFailed     = errors.New("发布消息失败")
	ErrSubscribeFailed   = errors.New("订阅队列失败")
)
