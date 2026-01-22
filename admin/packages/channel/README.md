# HuiZhiDa Channel Package

汇智答渠道领域包，提供渠道配置管理的核心功能。

## 功能特性

- 支持多种渠道类型：企业微信、淘宝/天猫、抖音、京东、拼多多、自定义Webhook
- 支持渠道配置管理（JSON格式，支持加密）
- 支持启用/禁用状态管理
- 支持一个应用配置多个渠道
- 每个应用每种渠道类型唯一

## 安装

在 `composer.json` 中添加：

```json
{
    "require": {
        "huizhida/channel": "*"
    },
    "repositories": [
        {
            "type": "path",
            "url": "./packages/*"
        }
    ]
}
```

然后运行：

```bash
composer require huizhida/channel
```

## 使用

### 数据库迁移

```bash
php artisan migrate
```

### 配置

配置文件位于 `config/channel.php`

### 领域模型

```php
use HuiZhiDa\Channel\Domain\Models\Channel;
use HuiZhiDa\Channel\Domain\Models\Enums\ChannelType;
use HuiZhiDa\Channel\Domain\Models\Enums\ChannelStatus;

$channel = new Channel();
$channel->app_id = 'app_001';
$channel->channel = ChannelType::WECOM;
$channel->config = [
    'corp_id' => 'xxx',
    'secret' => 'xxx',
];
$channel->status = ChannelStatus::ENABLED;
```

### 应用服务

```php
use HuiZhiDa\Channel\Application\Services\ChannelApplicationService;

$service = app(ChannelApplicationService::class);
$channels = $service->repository->findByAppId('app_001');
```

## 支持的渠道类型

- `wecom` - 企业微信
- `taobao` - 淘宝/天猫
- `douyin` - 抖音
- `jd` - 京东
- `pdd` - 拼多多
- `webhook` - 自定义Webhook

## 目录结构

```
channel/
├── composer.json
├── config/
│   └── channel.php
├── database/
│   └── migrations/
│       └── 2025_01_20_000002_create_channels_table.php
├── src/
│   ├── ChannelServiceProvider.php
│   ├── Application/
│   │   └── Services/
│   │       └── ChannelApplicationService.php
│   ├── Domain/
│   │   ├── Data/
│   │   │   └── ChannelData.php
│   │   ├── Models/
│   │   │   ├── Channel.php
│   │   │   └── Enums/
│   │   │       ├── ChannelStatus.php
│   │   │       └── ChannelType.php
│   │   ├── Repositories/
│   │   │   └── ChannelRepositoryInterface.php
│   │   └── Transformers/
│   │       └── ChannelTransformer.php
│   └── Infrastructure/
│       └── Repositories/
│           └── ChannelRepository.php
└── resources/
    └── lang/
```

## License

MIT
