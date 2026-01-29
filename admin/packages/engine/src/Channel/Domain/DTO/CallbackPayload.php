<?php

namespace HuiZhiDa\Engine\Channel\Domain\DTO;

use RedJasmine\Support\Foundation\Data\Data;

/**
 * 回调任务通用 DTO：渠道 ID + 渠道相关 payload，用于入队/出队序列化。
 */
class CallbackPayload extends Data
{
    /** 渠道 ID（Channel 主键） */
    public string $channelId;

    /** 渠道相关载荷，由各适配器在 extractCallbackPayload 中填充 */
    public array $payload = [];
}
