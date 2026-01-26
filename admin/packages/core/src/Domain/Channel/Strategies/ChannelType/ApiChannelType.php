<?php

namespace HuiZhiDa\Core\Domain\Channel\Strategies\ChannelType;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use HuiZhiDa\Core\Domain\Channel\Contracts\ChannelTypeInterface;
use HuiZhiDa\Core\Domain\Channel\DTO\Member;
use HuiZhiDa\Core\Domain\Channel\DTO\Receptionist;

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

    public function getMembers(array $params = []): array
    {
        return [];
    }

    public function getMemberDetail(string $memberId): ?Member
    {
        return null;
    }

    public function getDepartmentTree(): array
    {
        return [];
    }

    public function getApplications(array $params = []): array
    {
        return [];
    }

    public function getReceptionists(string $applicationId, array $params = []): array
    {
        return [];
    }

    public function addReceptionist(string $applicationId, Receptionist $receptionist): bool
    {
        return false;
    }

    public function removeReceptionist(string $applicationId, Receptionist $receptionist): bool
    {
        return false;
    }
}
