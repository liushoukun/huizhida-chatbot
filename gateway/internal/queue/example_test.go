package queue_test

import (
	"context"
	"encoding/json"
	"fmt"
	"time"

	"github.com/huizhida/gateway/internal/queue"
)

// ExampleRedisQueue 演示 Redis 队列的使用
func ExampleRedisQueue() {
	// 1. 创建 Redis 队列
	config := map[string]interface{}{
		"addr":     "localhost:6379",
		"password": "",
		"db":       0,
	}

	mq, err := queue.NewQueue("redis", config)
	if err != nil {
		fmt.Printf("创建队列失败: %v\n", err)
		return
	}
	defer mq.Close()

	ctx := context.Background()

	// 2. 发布消息
	message := map[string]interface{}{
		"message_id": "msg_001",
		"content":    "Hello, Redis Queue!",
		"timestamp":  time.Now().Unix(),
	}

	if err := mq.Publish(ctx, "test_queue", message); err != nil {
		fmt.Printf("发布消息失败: %v\n", err)
		return
	}

	fmt.Println("消息发布成功")

	// 3. 订阅消息
	msgChan, err := mq.Subscribe(ctx, "test_queue")
	if err != nil {
		fmt.Printf("订阅队列失败: %v\n", err)
		return
	}

	// 4. 消费消息
	select {
	case msg := <-msgChan:
		var receivedMsg map[string]interface{}
		if err := json.Unmarshal(msg.Data, &receivedMsg); err != nil {
			fmt.Printf("解析消息失败: %v\n", err)
			return
		}

		fmt.Printf("收到消息: %+v\n", receivedMsg)

		// 5. 确认消息
		if err := mq.Ack(ctx, msg); err != nil {
			fmt.Printf("确认消息失败: %v\n", err)
			return
		}

		fmt.Println("消息确认成功")

	case <-time.After(5 * time.Second):
		fmt.Println("等待消息超时")
	}
}

// ExampleRedisQueueMultipleConsumers 演示多个消费者
func ExampleRedisQueueMultipleConsumers() {
	config := map[string]interface{}{
		"addr": "localhost:6379",
		"db":   0,
	}

	mq, _ := queue.NewQueue("redis", config)
	defer mq.Close()

	ctx := context.Background()

	// 发布多条消息
	for i := 0; i < 10; i++ {
		message := map[string]interface{}{
			"id":      i,
			"content": fmt.Sprintf("Message %d", i),
		}
		mq.Publish(ctx, "multi_queue", message)
	}

	// 启动多个消费者
	for i := 0; i < 3; i++ {
		go func(consumerID int) {
			msgChan, _ := mq.Subscribe(ctx, "multi_queue")
			for msg := range msgChan {
				var data map[string]interface{}
				json.Unmarshal(msg.Data, &data)
				fmt.Printf("Consumer %d 处理消息: %+v\n", consumerID, data)
				mq.Ack(ctx, msg)
			}
		}(i)
	}

	time.Sleep(2 * time.Second)
}
