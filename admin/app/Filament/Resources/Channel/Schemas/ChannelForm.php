<?php

namespace App\Filament\Resources\Channel\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use HuiZhiDa\Channel\Domain\Models\Enums\ChannelStatus;
use HuiZhiDa\Channel\Domain\Strategies\ChannelType\ChannelTypeManager;
use InvalidArgumentException;
use RedJasmine\FilamentSupport\Resources\Schemas\Operators;

class ChannelForm
{
    /**
     * 配置表单
     */
    public static function configure(Schema $form): Schema
    {
        $schema = [
            Tab::make('basic_info')
                ->label('基础信息')
                ->columns(1)
                ->schema(static::basicInfoFields()),
            Tab::make('config')
                ->label('配置信息')
                ->columns(1)
                ->schema(static::configFields()),
        ];

        return $form
            ->components([
                Tabs::make('渠道')
                    ->tabs($schema)
                    ->persistTabInQueryString(),
            ])
            ->inlineLabel(true)
            ->columns(1);
    }

    /**
     * 基础信息字段
     */
    protected static function basicInfoFields(): array
    {
        return [
            Section::make('基础信息')
                ->description('设置渠道的基本信息')
                ->icon('heroicon-o-information-circle')
                ->columns(2)
                ->schema([
                    Select::make('app_id')
                        ->relationship('app','name')
                        ->label('应用ID')
                        ->required()

                        ->helperText('所属应用的ID')
                    ->dehydrateStateUsing(fn ($state) => (string)$state),

                    Select::make('agent_id')
                        ->relationship('agent', 'name')
                        ->label('绑定的智能体')
                        ->nullable()
                        ->searchable()
                        ->preload()
                        ->helperText('选择此渠道转发消息的智能体，不同渠道可以绑定不同的智能体'),

                    Select::make('channel')
                        ->label('渠道类型')
                        ->options(fn () => app(ChannelTypeManager::class)->options())
                        ->required()
                        ->reactive()
                        ->helperText('选择渠道类型'),

                    Toggle::make('status')
                        ->label('启用状态')
                        ->default(ChannelStatus::ENABLED->value)
                        ->helperText('是否启用此渠道'),
                ]),
        ];
    }

    /**
     * 配置信息字段
     */
    protected static function configFields(): array
    {
        return [
            Section::make('配置信息')
                ->description('配置渠道的详细参数')
                ->icon('heroicon-o-cog-6-tooth')
                ->columns(1)
                ->schema(function (Get $get) {
                    $channel = $get('channel');
                    
                    // 如果没有选择渠道类型，显示提示
                    if (!$channel) {
                        return [
                            TextInput::make('config_placeholder')
                                ->label('提示')
                                ->disabled()
                                ->default('请先选择渠道类型')
                                ->helperText('选择渠道类型后，将显示对应的配置字段'),
                        ];
                    }

                    // 根据渠道类型获取配置字段
                    try {
                        /** @var \HuiZhiDa\Channel\Domain\Contracts\ChannelTypeInterface $channelType */
                        $channelType = app(ChannelTypeManager::class)->create($channel);
                        $configFields = $channelType->getConfigFields();
                        
                        // 注意：getConfigFields() 返回的字段名称必须使用 'config.xxx' 格式
                        // Filament 会自动将值嵌套到 config 数组中
                        // 例如：'config.corp_id' 会映射到 $data['config']['corp_id']
                        return $configFields;
                    } catch (InvalidArgumentException $e) {
                        // 如果渠道类型不存在，显示 JSON 编辑器作为后备方案
                        return [
                            Textarea::make('config')
                                ->label('配置（JSON）')
                                ->rows(15)
                                ->helperText('渠道的配置信息，JSON格式。')
                                ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '')
                                ->dehydrateStateUsing(fn ($state) => $state ? json_decode($state, true) : null),
                        ];
                    }
                }),

            Operators::make(),
        ];
    }
}
