<?php

namespace HuiZhiDa\Channel\Domain\Models\Enums;

use RedJasmine\Support\Helpers\Enums\EnumsHelper;

enum ChannelType: string
{
    use EnumsHelper;

    case WECOM = 'wecom';
    case TAOBAO = 'taobao';
    case DOUYIN = 'douyin';
    case JD = 'jd';
    case PDD = 'pdd';
    case WEBHOOK = 'webhook';

    public static function labels(): array
    {
        return [
            self::WECOM->value => '企业微信',
            self::TAOBAO->value => '淘宝/天猫',
            self::DOUYIN->value => '抖音',
            self::JD->value => '京东',
            self::PDD->value => '拼多多',
            self::WEBHOOK->value => '自定义Webhook',
        ];
    }

    public static function colors(): array
    {
        return [
            self::WECOM->value => 'green',
            self::TAOBAO->value => 'orange',
            self::DOUYIN->value => 'purple',
            self::JD->value => 'red',
            self::PDD->value => 'pink',
            self::WEBHOOK->value => 'gray',
        ];
    }

    public static function icons(): array
    {
        return [
            self::WECOM->value => 'heroicon-o-chat-bubble-left-right',
            self::TAOBAO->value => 'heroicon-o-shopping-bag',
            self::DOUYIN->value => 'heroicon-o-video-camera',
            self::JD->value => 'heroicon-o-shopping-cart',
            self::PDD->value => 'heroicon-o-tag',
            self::WEBHOOK->value => 'heroicon-o-link',
        ];
    }
}
