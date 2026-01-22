package queue

import (
	"context"
	"fmt"
)

// NewKafkaQueue 创建 Kafka 队列（占位实现）
func NewKafkaQueue(config map[string]interface{}) (MessageQueue, error) {
	return nil, fmt.Errorf("Kafka 暂未实现")
}
