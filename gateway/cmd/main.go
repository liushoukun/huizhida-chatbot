package main

import (
	"context"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"github.com/gin-gonic/gin"
	"go.uber.org/zap"

	"github.com/huizhida/gateway/internal/config"
	"github.com/huizhida/gateway/internal/handler"
	"github.com/huizhida/gateway/internal/queue"
	"github.com/huizhida/gateway/internal/service"
)

func main() {
	// 初始化配置
	cfg, err := config.Load()
	if err != nil {
		log.Fatalf("加载配置失败: %v", err)
	}

	// 初始化日志
	logger, err := initLogger(cfg)
	if err != nil {
		log.Fatalf("初始化日志失败: %v", err)
	}
	defer logger.Sync()

	// 初始化数据库
	db, err := initDatabase(cfg)
	if err != nil {
		logger.Fatal("初始化数据库失败", zap.Error(err))
	}

	// 初始化 Redis
	redisClient, err := initRedis(cfg)
	if err != nil {
		logger.Fatal("初始化Redis失败", zap.Error(err))
	}

	// 初始化消息队列
	mq, err := queue.NewQueue(cfg.Queue.Type, cfg.Queue.Config)
	if err != nil {
		logger.Fatal("初始化消息队列失败", zap.Error(err))
	}
	defer mq.Close()

	// 初始化服务
	adapterFactory := service.NewAdapterFactory()
	sessionService := service.NewSessionService(db, logger)
	messageService := service.NewMessageService(db, logger)
	transferService := service.NewTransferService(adapterFactory, sessionService, messageService, logger)

	// 初始化处理器
	callbackHandler := handler.NewCallbackHandler(adapterFactory, sessionService, messageService, mq, logger)
	messageSender := handler.NewMessageSender(adapterFactory, messageService, mq, logger)
	transferExecutor := handler.NewTransferExecutor(transferService, mq, logger)

	// 初始化 HTTP 路由
	router := setupRouter(cfg, callbackHandler)

	// 启动消息发送消费者
	ctx, cancel := context.WithCancel(context.Background())
	defer cancel()

	go messageSender.Start(ctx)
	go transferExecutor.Start(ctx)

	// 启动 HTTP 服务器
	srv := &http.Server{
		Addr:    cfg.Server.Addr,
		Handler: router,
	}

	// 优雅关闭
	go func() {
		if err := srv.ListenAndServe(); err != nil && err != http.ErrServerClosed {
			logger.Fatal("HTTP服务器启动失败", zap.Error(err))
		}
	}()

	logger.Info("消息网关服务启动成功", zap.String("addr", cfg.Server.Addr))

	// 等待中断信号
	quit := make(chan os.Signal, 1)
	signal.Notify(quit, syscall.SIGINT, syscall.SIGTERM)
	<-quit

	logger.Info("正在关闭服务器...")

	// 停止消费者
	cancel()

	// 关闭 HTTP 服务器
	ctx, cancel = context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	if err := srv.Shutdown(ctx); err != nil {
		logger.Error("服务器强制关闭", zap.Error(err))
	}

	logger.Info("服务器已关闭")
}

func setupRouter(cfg *config.Config, callbackHandler *handler.CallbackHandler) *gin.Engine {
	if cfg.Server.Mode == "release" {
		gin.SetMode(gin.ReleaseMode)
	}

	router := gin.New()
	router.Use(gin.Logger())
	router.Use(gin.Recovery())

	// 健康检查
	router.GET("/health", func(c *gin.Context) {
		c.JSON(200, gin.H{"status": "ok"})
	})

	// 回调接口
	api := router.Group("/api")
	{
		api.POST("/callback/:channel/:app_id", callbackHandler.HandleCallback)
	}

	return router
}

func initLogger(cfg *config.Config) (*zap.Logger, error) {
	var logger *zap.Logger
	var err error

	if cfg.Server.Mode == "release" {
		logger, err = zap.NewProduction()
	} else {
		logger, err = zap.NewDevelopment()
	}

	return logger, err
}

func initDatabase(cfg *config.Config) (*gorm.DB, error) {
	if cfg.Database.DSN == "" {
		return nil, nil // 数据库可选
	}

	db, err := gorm.Open(mysql.Open(cfg.Database.DSN), &gorm.Config{})
	if err != nil {
		return nil, err
	}

	// 自动迁移（可选）
	// db.AutoMigrate(&model.Session{}, &model.Message{})

	return db, nil
}

func initRedis(cfg *config.Config) (*redis.Client, error) {
	client := redis.NewClient(&redis.Options{
		Addr:     cfg.Redis.Addr,
		Password: cfg.Redis.Password,
		DB:       cfg.Redis.DB,
	})

	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()

	if err := client.Ping(ctx).Err(); err != nil {
		return nil, err
	}

	return client, nil
}
