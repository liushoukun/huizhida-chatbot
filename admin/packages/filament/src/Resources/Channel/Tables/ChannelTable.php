<?php

namespace HuiZhiDa\Filament\Resources\Channel\Tables;

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
use HuiZhiDa\Filament\Resources\Channel\ChannelResource;
use HuiZhiDa\Core\Domain\Channel\Models\Enums\ChannelStatus;
use HuiZhiDa\Core\Domain\Channel\Strategies\ChannelType\ChannelTypeManager;
use InvalidArgumentException;

class ChannelTable
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
            TextColumn::make('id')
                ->label('ID')
                ->copyable()
                ->sortable()
                ->searchable()
                ->icon('heroicon-o-identification')
                ->color('gray')
                ->size('xs'),

            TextColumn::make('app_id')
                ->label('应用ID')
                ->copyable()
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('app.name')
                ->label('应用名称')

                ->searchable()
                ->sortable()
                ->weight('bold'),

            TextColumn::make('agent.name')
                ->label('智能体名称')

                ->searchable()
                ->sortable()
                ->placeholder('未绑定')
                ->formatStateUsing(fn ($state) => $state ?? '未绑定'),

            TextColumn::make('channel')
                ->label('渠道类型')
                ->badge()
                ->formatStateUsing(function ($state) {
                    if (!$state) {
                        return null;
                    }
                    
                    // 处理枚举类型：如果是枚举，获取其 value
                    $channelValue = $state instanceof \BackedEnum ? $state->value : (string) $state;
                    
                    if (!$channelValue) {
                        return null;
                    }
                    
                    try {
                        $type = app(ChannelTypeManager::class)->create($channelValue);
                        return $type->label();
                    } catch (InvalidArgumentException $e) {
                        return $channelValue;
                    }
                })
                ->color(function ($state) {
                    if (!$state) {
                        return null;
                    }
                    
                    // 处理枚举类型：如果是枚举，获取其 value
                    $channelValue = $state instanceof \BackedEnum ? $state->value : (string) $state;
                    
                    if (!$channelValue) {
                        return null;
                    }
                    
                    try {
                        $type = app(ChannelTypeManager::class)->create($channelValue);
                        return $type->color() ?? 'gray';
                    } catch (InvalidArgumentException $e) {
                        return 'gray';
                    }
                }),

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
            SelectFilter::make('channel')
                ->multiple()
                ->label('渠道类型')
                ->options(fn () => app(ChannelTypeManager::class)->options()),

            SelectFilter::make('status')
                ->multiple()
                ->label('状态')
                ->options(ChannelStatus::options()),

            SelectFilter::make('app_id')
                ->label('应用ID')
                ->searchable(),
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
