<?php

namespace HuiZhiDa\Filament\Resources\Channel\Actions;

use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use HuiZhiDa\Core\Domain\Channel\Contracts\ChannelTypeInterface;
use HuiZhiDa\Core\Domain\Channel\Strategies\ChannelType\ChannelTypeManager;

class ViewServiceApplicationsAction
{
    /**
     * 创建查看客服应用的操作
     */
    public static function make(): Action
    {
        return Action::make('view_service_applications')
            ->label('查看客服应用')
            ->icon('heroicon-o-squares-2x2')
            ->color('info')
            ->modalHeading('客服应用列表')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('关闭')
            ->infolist(function ($record) {
                // 获取数据
                $data = static::getApplicationsData($record);

                // 如果有错误，显示错误信息
                if (isset($data['error'])) {
                    return [
                        TextEntry::make('error')
                            ->label('错误')
                            ->default($data['error'])
                            ->color('danger'),
                    ];
                }

                // 显示客服应用列表
                return [
                    RepeatableEntry::make('applications')
                        ->label('客服应用')
                        ->schema([
                            TextEntry::make('id')
                                ->label('应用ID')
                                ->copyable()
                                ->icon('heroicon-o-identification'),
                            TextEntry::make('name')
                                ->label('应用名称')
                                ->placeholder('未命名'),
                        ])
                        ->columns(2)
                        ->default($data['applications'] ?? []),
                ];
            })
            ->visible(function ($record) {
                // 只对已配置的渠道显示
                return !empty($record->config) && !empty($record->channel);
            });
    }

    /**
     * 获取客服应用数据
     */
    protected static function getApplicationsData($record): array
    {
        try {
            // 获取渠道类型
            $channelValue = $record->channel instanceof \BackedEnum 
                ? $record->channel->value 
                : (string) $record->channel;

            if (!$channelValue) {
                return ['error' => '渠道类型未设置'];
            }

            // 创建渠道类型实例
            $channelTypeManager = app(ChannelTypeManager::class);
            $channelType = $channelTypeManager->create($channelValue);

            // 检查是否实现了获取客服应用的方法
            if (!($channelType instanceof ChannelTypeInterface)) {
                return ['error' => '该渠道类型不支持查看客服应用'];
            }

            // 设置配置
            $config = $record->config ?? [];
            /** @var \HuiZhiDa\Core\Domain\Channel\Strategies\ChannelType\WorkWechatChannelType $channelType */
            if (method_exists($channelType, 'setConfig')) {
                $channelType->setConfig($config);
            }

            // 获取客服应用列表
            $applications = $channelType->getApplications();

            // 转换为数组格式供 RepeatableEntry 使用
            $applicationsData = array_map(function ($app) {
                return [
                    'id' => $app->id,
                    'name' => $app->name ?? '未命名',
                ];
            }, $applications);

            return ['applications' => $applicationsData];
        } catch (\Exception $e) {
            return ['error' => '获取客服应用失败: ' . $e->getMessage()];
        }
    }
}
