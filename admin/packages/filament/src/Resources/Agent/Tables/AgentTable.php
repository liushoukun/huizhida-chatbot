<?php

namespace HuiZhiDa\Filament\Resources\Agent\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use HuiZhiDa\Filament\Resources\Agent\AgentResource;
use HuiZhiDa\Core\Domain\Agent\Models\Enums\AgentStatus;
use HuiZhiDa\Core\Domain\Agent\Strategies\AgentType\AgentTypeManager;
use InvalidArgumentException;

class AgentTable
{
    /**
     * 配置表格
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns(static::getColumns())
            ->filters(static::getFilters(), layout: FiltersLayout::AboveContentCollapsible)
            ->deferFilters()
            ->recordUrl(null)
            ->recordActions(static::getRecordActions())
            ->toolbarActions(static::getToolbarActions());
    }

    /**
     * 获取表格列
     */
    protected static function getColumns(): array
    {
        return [
            ...AgentResource::ownerTableColumns(),

            TextColumn::make('id')
                ->label('ID')
                ->copyable()
                ->sortable()
                ->searchable()
                ->icon('heroicon-o-identification')
                ->color('gray')
                ->size('xs'),

            TextColumn::make('name')
                ->label('智能体名称')
                ->copyable()
                ->searchable()
                ->limit(30)
                ->weight('bold'),

            TextColumn::make('agent_type')
                ->label('类型')
                ->badge()
                ->formatStateUsing(function ($state) {
                    if (!$state) {
                        return null;
                    }
                    
                    // 处理枚举类型：如果是枚举，获取其 value
                    $agentTypeValue = $state instanceof \BackedEnum ? $state->value : (string) $state;
                    
                    if (!$agentTypeValue) {
                        return null;
                    }
                    
                    try {
                        $type = app(AgentTypeManager::class)->create($agentTypeValue);
                        return $type->label();
                    } catch (InvalidArgumentException $e) {
                        return $agentTypeValue;
                    }
                })
                ->color(function ($state) {
                    if (!$state) {
                        return null;
                    }
                    
                    // 处理枚举类型：如果是枚举，获取其 value
                    $agentTypeValue = $state instanceof \BackedEnum ? $state->value : (string) $state;
                    
                    if (!$agentTypeValue) {
                        return null;
                    }
                    
                    try {
                        $type = app(AgentTypeManager::class)->create($agentTypeValue);
                        return $type->color() ?? 'gray';
                    } catch (InvalidArgumentException $e) {
                        return 'gray';
                    }
                }),

            TextColumn::make('provider')
                ->label('提供者')
                ->searchable()
                ->sortable()
                ->placeholder('未设置'),

            TextColumn::make('fallbackAgent.name')
                ->label('降级智能体')
                ->searchable()
                ->sortable()
                ->placeholder('无'),

            TextColumn::make('status')
                ->label('状态')
                ->badge()
                ->formatStateUsing(fn ($state) => $state?->getLabel() ?? '')
                ->color(fn ($state) => $state?->getColor() ?? 'gray'),

            TextColumn::make('created_at')
                ->label('创建时间')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('更新时间')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * 获取筛选器
     */
    protected static function getFilters(): array
    {
        return [
            SelectFilter::make('agent_type')
                ->multiple()
                ->label('智能体类型')
                ->options(fn () => app(AgentTypeManager::class)->options()),

            SelectFilter::make('status')
                ->multiple()
                ->label('状态')
                ->options(AgentStatus::options()),
        ];
    }

    /**
     * 获取记录操作
     */
    protected static function getRecordActions(): array
    {
        return [
            EditAction::make(),
            ActionGroup::make([
                ViewAction::make(),
                DeleteAction::make(),
            ])
                ->visible(static function ($record): bool {
                    if (method_exists($record, 'trashed')) {
                        return !$record->trashed();
                    }
                    return true;
                }),
            RestoreAction::make(),
        ];
    }

    /**
     * 获取工具栏操作
     */
    protected static function getToolbarActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),
            ]),
        ];
    }
}
