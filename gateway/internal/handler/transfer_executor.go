package handler

import (
	"context"

	"go.uber.org/zap"

	"github.com/huizhida/gateway/internal/model"
	"github.com/huizhida/gateway/internal/queue"
	"github.com/huizhida/gateway/internal/service"
)

// TransferExecutor 转人工执行器（消费转人工队列）
type TransferExecutor struct {
	transferService *service.TransferService
	mq               queue.MessageQueue
	logger           *zap.Logger
}

// NewTransferExecutor 创建转人工执行器
func NewTransferExecutor(
	transferService *service.TransferService,
	mq queue.MessageQueue,
	logger *zap.Logger,
) *TransferExecutor {
	return &TransferExecutor{
		transferService: transferService,
		mq:               mq,
		logger:           logger,
	}
}

// Start 启动转人工消费者
func (t *TransferExecutor) Start(ctx context.Context) {
	msgChan, err := t.mq.Subscribe(ctx, "transfer_requests")
	if err != nil {
		t.logger.Fatal("订阅转人工队列失败", zap.Error(err))
		return
	}

	t.logger.Info("转人工消费者已启动")

	for {
		select {
		case <-ctx.Done():
			t.logger.Info("转人工消费者已停止")
			return
		case msg, ok := <-msgChan:
			if !ok {
				t.logger.Warn("消息通道已关闭")
				return
			}

			t.handleTransfer(ctx, msg)
		}
	}
}

// handleTransfer 处理转人工请求
func (t *TransferExecutor) handleTransfer(ctx context.Context, queueMsg queue.Message) {
	var transferReq model.TransferRequest
	if err := queue.DeserializeMessage(queueMsg.Data, &transferReq); err != nil {
		t.logger.Error("反序列化转人工请求失败", zap.Error(err))
		t.mq.Nack(ctx, queueMsg)
		return
	}

	// 执行转人工
	if err := t.transferService.Execute(transferReq.SessionID, &transferReq); err != nil {
		t.logger.Error("执行转人工失败", zap.Error(err))
		t.mq.Nack(ctx, queueMsg)
		return
	}

	// 确认消费
	if err := t.mq.Ack(ctx, queueMsg); err != nil {
		t.logger.Warn("确认消息失败", zap.Error(err))
	}

	t.logger.Info("转人工执行成功",
		zap.String("session_id", transferReq.SessionID),
		zap.String("reason", transferReq.Reason),
		zap.String("source", transferReq.Source),
	)
}
