<?php

namespace HuiZhiDa\Core\Domain\Agent\Strategies\AgentType;

use Filament\Forms\Components\TextInput;
use HuiZhiDa\Core\Domain\Agent\Contracts\AgentTypeInterface;

/**
 * Coze 扣子智能体类型
 */
class CozeAgentType implements AgentTypeInterface
{
    public function value(): string
    {
        return 'coze';
    }

    public function label(): string
    {
        return 'Coze 扣子';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public function color(): ?string
    {
        return 'orange';
    }

    public function tips(): ?string
    {
        return null;
    }

    public function disabled(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [
            TextInput::make('config.bot_id')
                ->label('Bot ID')
                ->required()
                ->helperText('Coze 智能体 ID，从空间/机器人页 URL 的 bot 参数获取'),
            TextInput::make('config.pat_token')
                ->label('PAT 令牌')
                ->required()
                ->password()
                ->helperText('Coze 开发者设置中创建的 API 令牌，用于 Bearer 认证'),
        ];
    }
}
