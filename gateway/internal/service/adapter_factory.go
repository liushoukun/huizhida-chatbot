package service

import (
	"github.com/huizhida/gateway/internal/adapter"
)

// NewAdapterFactory 创建适配器工厂（包装 adapter 包的工厂）
func NewAdapterFactory() *adapter.AdapterFactory {
	return adapter.NewAdapterFactory()
}
