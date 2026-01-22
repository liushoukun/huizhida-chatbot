package queue

import (
	"context"
	"fmt"
)

// NewRabbitMQ 创建 RabbitMQ 队列（占位实现）
func NewRabbitMQ(config map[string]interface{}) (MessageQueue, error) {
	return nil, fmt.Errorf("RabbitMQ 暂未实现")
}
