<?php

namespace HuiZhiDa\Processor\Infrastructure\Utils;

use Psr\Http\Message\StreamInterface;

/**
 * 流式响应解析工具类
 */
class StreamResponseParser
{
    /**
     * 解析流式响应
     *
     * @param StreamInterface $stream 响应流
     * @return array 返回解析后的数据数组，包含：
     *               - content: 累积的内容字符串
     *               - conversation_id: 会话ID（如果有）
     *               - raw_data: 所有解析的原始数据数组
     */
    public static function parse(StreamInterface $stream): array
    {
        $content = '';
        $conversationId = null;
        $rawData = [];
        $buffer = '';

        // 逐块读取流式数据
        while (!$stream->eof()) {
            $chunk = $stream->read(1024); // 每次读取 1KB
            if ($chunk === '') {
                break;
            }

            $buffer .= $chunk;

            // 处理 SSE 格式的数据（每行以 data: 开头）
            $lines = explode("\n", $buffer);
            // 保留最后一个不完整的行
            $buffer = array_pop($lines);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // 处理 SSE 格式：data: {...}
                if (strpos($line, 'data: ') === 0) {
                    $jsonStr = substr($line, 6); // 移除 "data: " 前缀
                    if ($jsonStr === '[DONE]') {
                        // 流结束标记
                        break 2;
                    }

                    $data = json_decode($jsonStr, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                        // 保存原始数据
                        $rawData[] = $data;
                        
                        // 累积内容
                        if (isset($data['content'])) {
                            $content .= $data['content'];
                        }
                        // 更新会话ID（如果返回了新的）
                        if (isset($data['conversation_id'])) {
                            $conversationId = $data['conversation_id'];
                        }
                    }
                } elseif (strpos($line, '{') === 0) {
                    // 如果不是 SSE 格式，直接尝试解析 JSON
                    $data = json_decode($line, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                        // 保存原始数据
                        $rawData[] = $data;
                        
                        if (isset($data['content'])) {
                            $content .= $data['content'];
                        }
                        if (isset($data['conversation_id'])) {
                            $conversationId = $data['conversation_id'];
                        }
                    }
                }
            }
        }

        // 处理缓冲区中剩余的数据
        if (!empty($buffer)) {
            $buffer = trim($buffer);
            if (strpos($buffer, 'data: ') === 0) {
                $jsonStr = substr($buffer, 6);
                $data = json_decode($jsonStr, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    // 保存原始数据
                    $rawData[] = $data;
                    
                    if (isset($data['content'])) {
                        $content .= $data['content'];
                    }
                    if (isset($data['conversation_id'])) {
                        $conversationId = $data['conversation_id'];
                    }
                }
            }
        }

        return [
            'content'         => $content,
            'conversation_id' => $conversationId,
            'raw_data'        => $rawData,
        ];
    }
}
