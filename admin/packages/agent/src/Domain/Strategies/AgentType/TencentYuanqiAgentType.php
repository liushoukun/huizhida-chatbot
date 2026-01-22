<?php

namespace HuiZhiDa\Agent\Domain\Strategies\AgentType;

use Filament\Forms\Components\TextInput;
use HuiZhiDa\Agent\Domain\Contracts\AgentTypeInterface;

/**
 * 腾讯元器智能体类型
 */
class TencentYuanqiAgentType implements AgentTypeInterface
{
    public function value(): string
    {
        return 'tencent_yuanqi';
    }

    public function label(): string
    {
        return '腾讯元器';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-sparkles';
    }

    public function color(): ?string
    {
        return 'blue';
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
            TextInput::make('config.app_id')
                ->label('应用ID')
                ->required()
                ->helperText('腾讯元器应用ID'),
            TextInput::make('config.app_key')
                ->label('应用Key')
                ->required()
                ->helperText('腾讯元器应用Key'),
        ];
    }
}
