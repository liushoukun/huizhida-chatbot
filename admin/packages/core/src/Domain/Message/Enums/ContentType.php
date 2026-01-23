<?php

namespace HuiZhiDa\Core\Domain\Message\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum ContentType: string
{
    use EnumsHelper;

    // 组合
    case Combination = 'combination';

    // 文本
    case Text = 'text';
    // 图片
    case Image = 'image';
    // 语音
    case Voice = 'voice';
    // 视频
    case Video = 'video';
    // 文件
    case File = 'file';


    // 卡片
    case Card = 'card';
    // 事件
    case Event = 'event';


    // 未知
    case Unknown = 'unknown';

}
