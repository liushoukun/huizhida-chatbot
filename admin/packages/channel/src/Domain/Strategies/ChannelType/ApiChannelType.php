<?php

namespace HuiZhiDa\Channel\Domain\Strategies\ChannelType;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use HuiZhiDa\Channel\Domain\Contracts\ChannelTypeInterface;

/**
 * API渠道类型
 */
class ApiChannelType implements ChannelTypeInterface
{
    public function value(): string
    {
        return 'api';
    }

    public function label(): string
    {
        return 'API渠道';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-code-bracket';
    }

    public function color(): ?string
    {
        return 'blue';
    }

    public function tips(): ?string
    {
        return '通过API接口接收和发送消息的渠道类型';
    }

    public function disabled(): bool
    {
        return false;
    }

    public function getConfigFields(): array
    {
        return [
            TextInput::make('config.api_url')
                ->label('API地址')
                ->url()
                ->required()
                ->helperText('API渠道的回调地址'),
            TextInput::make('config.api_key')
                ->label('API Key')
                ->helperText('API渠道的认证Key（可选）'),
            TextInput::make('config.api_secret')
                ->label('API Secret')
                ->password()
                ->helperText('API渠道的认证密钥（可选）'),
            Textarea::make('config.api_config')
                ->label('额外配置')
                ->rows(5)
                ->helperText('额外的API配置信息（JSON格式）'),
        ];
    }
}
