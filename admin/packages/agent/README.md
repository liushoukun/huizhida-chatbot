# HuiZhiDa Agent Package

汇智答智能体领域包，提供智能体管理的核心功能。

## 功能特性

- 支持三种智能体类型：本地智能体（Local）、远程智能体（Remote）、组合智能体（Hybrid）
- 支持多种提供者：Ollama、OpenAI、通义千问、Coze 等
- 支持降级智能体配置
- 支持启用/禁用状态管理
- 支持多所有者（Owner）管理

## 安装

在 `composer.json` 中添加：

```json
{
    "require": {
        "huizhida/agent": "*"
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
composer require huizhida/agent
```

## 使用

### 数据库迁移

```bash
php artisan migrate
```

### 配置

配置文件位于 `config/agent.php`

### 领域模型

```php
use HuiZhiDa\Agent\Domain\Models\Agent;
use HuiZhiDa\Agent\Domain\Models\Enums\AgentType;
use HuiZhiDa\Agent\Domain\Models\Enums\AgentStatus;

$agent = new Agent();
$agent->name = 'OpenAI GPT-4';
$agent->agent_type = AgentType::REMOTE;
$agent->provider = 'openai';
$agent->status = AgentStatus::ENABLED;
```

### 应用服务

```php
use HuiZhiDa\Agent\Application\Services\AgentApplicationService;

$service = app(AgentApplicationService::class);
$agents = $service->repository->findByOwner($user);
```

## 目录结构

```
agent/
├── composer.json
├── config/
│   └── agent.php
├── database/
│   └── migrations/
│       └── 2025_01_20_000001_create_agents_table.php
├── src/
│   ├── AgentServiceProvider.php
│   ├── Application/
│   │   └── Services/
│   │       └── AgentApplicationService.php
│   ├── Domain/
│   │   ├── Data/
│   │   │   └── AgentData.php
│   │   ├── Models/
│   │   │   ├── Agent.php
│   │   │   └── Enums/
│   │   │       ├── AgentStatus.php
│   │   │       └── AgentType.php
│   │   ├── Repositories/
│   │   │   └── AgentRepositoryInterface.php
│   │   └── Transformers/
│   │       └── AgentTransformer.php
│   └── Infrastructure/
│       └── Repositories/
│           └── AgentRepository.php
└── resources/
    └── lang/
```

## License

MIT
