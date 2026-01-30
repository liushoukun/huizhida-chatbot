<?php

namespace HuiZhiDa\Filament\Resources\Agent\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use HuiZhiDa\Core\Domain\Agent\Models\Agent;
use HuiZhiDa\Core\Domain\Agent\Models\Enums\AgentStatus;
use HuiZhiDa\Core\Domain\Agent\Strategies\AgentType\AgentTypeManager;
use InvalidArgumentException;
use RedJasmine\FilamentSupport\Resources\Schemas\Operators;
use RedJasmine\FilamentSupport\Resources\Schemas\Owner;

class AgentForm
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
                Tabs::make('智能体')
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
            Owner::make(),

            Section::make('基础信息')
                ->description('设置智能体的基本信息')
                ->icon('heroicon-o-information-circle')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('智能体名称')
                        ->required()
                        ->maxLength(100)
                        ->helperText('智能体的显示名称'),

                    Select::make('agent_type')
                        ->label('智能体类型')
                        ->options(fn () => app(AgentTypeManager::class)->options())
                        ->required()
                        ->reactive()
                        ->helperText('选择智能体类型'),



                    Toggle::make('status')
                        ->label('启用状态')
                        ->default(AgentStatus::ENABLED->value)
                        ->helperText('是否启用此智能体'),
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
                ->description('配置智能体的详细参数')
                ->icon('heroicon-o-cog-6-tooth')
                ->columns(1)
                ->schema(function (Get $get) {
                    $agentType = $get('agent_type');
                    
                    // 如果没有选择智能体类型，显示提示
                    if (!$agentType) {
                        return [
                            TextInput::make('config_placeholder')
                                ->label('提示')
                                ->disabled()
                                ->default('请先选择智能体类型')
                                ->helperText('选择智能体类型后，将显示对应的配置字段'),
                        ];
                    }

                    // 根据智能体类型获取配置字段
                    try {
                        /** @var \HuiZhiDa\Core\Domain\Agent\Contracts\AgentTypeInterface $agentTypeInstance */
                        $agentTypeInstance = app(AgentTypeManager::class)->create($agentType);
                        $configFields = $agentTypeInstance->getConfigFields();
                        
                        // 注意：getConfigFields() 返回的字段名称必须使用 'config.xxx' 格式
                        // Filament 会自动将值嵌套到 config 数组中
                        // 例如：'config.app_id' 会映射到 $data['config']['app_id']
                        return $configFields;
                    } catch (InvalidArgumentException $e) {
                        // 如果智能体类型不存在，显示 JSON 编辑器作为后备方案
                        return [
                            Textarea::make('config')
                                ->label('配置（JSON）')
                                ->rows(10)
                                ->helperText('智能体的配置信息，JSON格式。')
                                ->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '')
                                ->dehydrateStateUsing(fn ($state) => $state ? json_decode($state, true) : null),
                        ];
                    }
                }),

            Operators::make(),
        ];
    }
}
