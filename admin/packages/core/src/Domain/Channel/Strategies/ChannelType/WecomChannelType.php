<?php

namespace HuiZhiDa\Core\Domain\Channel\Strategies\ChannelType;

use Filament\Forms\Components\TextInput;
use HuiZhiDa\Core\Domain\Channel\Contracts\ChannelTypeInterface;

/**
 * 企业微信渠道类型
 */
class WecomChannelType implements ChannelTypeInterface
{
    public function value(): string
    {
        return 'wecom';
    }

    public function label(): string
    {
        return '企业微信';
    }

    public function icon(): ?string
    {
        return 'heroicon-o-chat-bubble-left-right';
    }

    public function color(): ?string
    {
        return 'green';
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
            TextInput::make('config.corp_id')
                ->label('企业ID')
                ->required()
                ->helperText('企业微信的企业ID'),
            TextInput::make('config.agent_id')
                     ->label('应用ID')
                     ->required()
                     ->helperText('企业微信应用的Secret'),
            TextInput::make('config.secret')
                ->label('应用Secret')
                ->required()
                ->helperText('企业微信应用的Secret'),
            TextInput::make('config.token')
                ->label('回调Token')
                ->required()
                ->helperText('企业微信回调验证的Token'),
            TextInput::make('config.encoding_aes_key')
                ->label('加密Key')
                ->required()
                ->helperText('企业微信消息加解密的EncodingAESKey'),
        ];
    }
}
