package queue

import (
	"context"
	"encoding/json"
	"fmt"
	"time"

	"github.com/go-redis/redis/v8"
)

// RedisQueue Redis Streams 实现
type RedisQueue struct {
	client *redis.Client
	ctx    context.Context
}

// NewRedisQueue 创建 Redis 队列
func NewRedisQueue(config map[string]interface{}) (MessageQueue, error) {
	addr, _ := config["addr"].(string)
	if addr == "" {
		addr = "localhost:6379"
	}

	password, _ := config["password"].(string)
	db, _ := config["db"].(int)
	if db < 0 {
		db = 0
	}

	client := redis.NewClient(&redis.Options{
		Addr:         addr,
		Password:     password,
		DB:           db,
		PoolSize:     10,
		MinIdleConns: 5,
	})

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := client.Ping(ctx).Err(); err != nil {
		return nil, fmt.Errorf("连接Redis失败: %w", err)
	}

	return &RedisQueue{
		client: client,
		ctx:    context.Background(),
	}, nil
}

// Publish 发布消息
func (r *RedisQueue) Publish(ctx context.Context, queue string, message interface{}) error {
	data, err := SerializeMessage(message)
	if err != nil {
		return fmt.Errorf("%w: %v", ErrPublishFailed, err)
	}

	args := &redis.XAddArgs{
		Stream: queue,
		Values: map[string]interface{}{
			"data": string(data),
		},
	}

	if err := r.client.XAdd(ctx, args).Err(); err != nil {
		return fmt.Errorf("%w: %v", ErrPublishFailed, err)
	}

	return nil
}

// Subscribe 订阅队列
func (r *RedisQueue) Subscribe(ctx context.Context, queue string) (<-chan Message, error) {
	msgChan := make(chan Message, 100)

		go func() {
		defer close(msgChan)
		consumerGroup := fmt.Sprintf("%s-group", queue)
		consumerName := fmt.Sprintf("consumer-%d", time.Now().UnixNano())

		// 创建消费者组（如果不存在）
		// 忽略 BUSYGROUP 错误（组已存在）
		err := r.client.XGroupCreateMkStream(ctx, queue, consumerGroup, "0").Err()
		if err != nil && err.Error() != "BUSYGROUP Consumer Group name already exists" {
			// 如果创建失败且不是已存在的错误，记录但不影响运行
			// 在实际应用中可以使用 logger
		}

		for {
			select {
			case <-ctx.Done():
				return
			default:
				// 读取消息
				streams, err := r.client.XReadGroup(ctx, &redis.XReadGroupArgs{
					Group:    consumerGroup,
					Consumer: consumerName,
					Streams:  []string{queue, ">"},
					Count:    10,
					Block:    time.Second,
				}).Result()

				if err != nil {
					if err == redis.Nil {
						continue
					}
					// 如果是上下文取消，直接返回
					if err == context.Canceled || err == context.DeadlineExceeded {
						return
					}
					// 其他错误，记录但继续
					continue
				}

				for _, stream := range streams {
					for _, xMessage := range stream.Messages {
						dataStr, ok := xMessage.Values["data"].(string)
						if !ok {
							continue
						}

						msg := Message{
							ID:    xMessage.ID,
							Queue: queue,
							Data:  []byte(dataStr),
							Headers: map[string]string{
								"consumer_group": consumerGroup,
								"consumer":       consumerName,
							},
						}
						select {
						case msgChan <- msg:
						case <-ctx.Done():
							return
						}
					}
				}
			}
		}
	}()

	return msgChan, nil
}

// Ack 确认消息
func (r *RedisQueue) Ack(ctx context.Context, msg Message) error {
	consumerGroup := msg.Headers["consumer_group"]
	if consumerGroup == "" {
		// 如果没有 consumer_group，使用默认格式
		consumerGroup = fmt.Sprintf("%s-group", msg.Queue)
	}

	return r.client.XAck(ctx, msg.Queue, consumerGroup, msg.ID).Err()
}

// Nack 拒绝消息
func (r *RedisQueue) Nack(ctx context.Context, msg Message) error {
	// Redis Streams 不支持 Nack，可以：
	// 1. 不确认消息，让它自动重新投递（PENDING 状态）
	// 2. 或者将消息重新发布到队列
	// 这里选择方案1：不确认消息，让它保持在 PENDING 状态，稍后会自动重新投递

	// 也可以选择方案2：重新发布消息
	// 解析消息数据并重新发布
	var messageData interface{}
	if err := json.Unmarshal(msg.Data, &messageData); err == nil {
		return r.Publish(ctx, msg.Queue, messageData)
	}

	// 如果解析失败，至少不确认消息，让它保持在 PENDING 状态
	return nil
}

// Close 关闭连接
func (r *RedisQueue) Close() error {
	return r.client.Close()
}
