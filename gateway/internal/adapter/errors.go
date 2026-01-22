package adapter

import "errors"

var (
	ErrUnsupportedChannel = errors.New("不支持的渠道类型")
	ErrInvalidSignature   = errors.New("签名验证失败")
	ErrParseMessage       = errors.New("消息解析失败")
	ErrSendMessage        = errors.New("消息发送失败")
	ErrTransferFailed     = errors.New("转人工失败")
)
