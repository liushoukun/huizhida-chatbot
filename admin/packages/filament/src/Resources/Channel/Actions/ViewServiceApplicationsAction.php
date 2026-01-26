<?php

namespace HuiZhiDa\Filament\Resources\Channel\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use HuiZhiDa\Core\Domain\Channel\Contracts\ChannelTypeInterface;
use HuiZhiDa\Core\Domain\Channel\Strategies\ChannelType\ChannelTypeManager;
use HuiZhiDa\Core\Domain\Channel\DTO\ReceptionistTypeEnum;
use HuiZhiDa\Core\Domain\Channel\DTO\ReceptionistStatusEnum;
use HuiZhiDa\Core\Domain\Channel\DTO\Receptionist;

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
            ->registerModalActions([
                static::getViewReceptionistsAction(),
            ])
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
                $applications = $data['applications'] ?? [];

                return [
                    ViewEntry::make('applications')
                        ->label('客服应用')
                        ->view('filament.resources.channel.actions.view-service-applications-table')
                        ->viewData([
                            'applications' => $applications,
                            'record' => $record,
                        ]),
                ];
            })
            ->visible(function ($record) {
                // 只对已配置的渠道显示
                return !empty($record->config) && !empty($record->channel);
            });
    }

    /**
     * 获取查看接待人员的操作
     */
    protected static function getViewReceptionistsAction(): Action
    {
        return Action::make('view_receptionists')
            ->label('查看接待人员')
            ->icon('heroicon-o-users')
            ->color('info')
            ->modalHeading(fn (array $arguments) => '接待人员列表 - ' . ($arguments['applicationName'] ?? ''))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('关闭')
            ->registerModalActions([
                static::getAddReceptionistAction(),
                static::getRemoveReceptionistAction(),
            ])
            ->infolist(function ($record, array $arguments) {
                $applicationId = $arguments['applicationId'] ?? '';
                $receptionistsData = static::getReceptionistsData($record, $applicationId);

                // 如果有错误，显示错误信息
                if (isset($receptionistsData['error'])) {
                    return [
                        TextEntry::make('error')
                            ->label('错误')
                            ->default($receptionistsData['error'])
                            ->color('danger'),
                    ];
                }

                // 显示接待人员列表
                return [
                    ViewEntry::make('receptionists')
                        ->label('接待人员')
                        ->view('filament.resources.channel.actions.view-receptionists-table')
                        ->viewData([
                            'receptionists' => $receptionistsData['receptionists'] ?? [],
                            'record' => $record,
                            'applicationId' => $applicationId,
                            'applicationName' => $arguments['applicationName'] ?? '',
                        ]),
                ];
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

    /**
     * 获取接待人员数据
     */
    protected static function getReceptionistsData($record, string $applicationId): array
    {
        try {
            // 获取渠道类型
            $channelValue = $record->channel instanceof \BackedEnum
                ? $record->channel->value
                : (string) $record->channel;

            if (!$channelValue) {
                return ['error' => '渠道类型未设置'];
            }

            if (!$applicationId) {
                return ['error' => '应用ID未提供'];
            }

            // 创建渠道类型实例
            $channelTypeManager = app(ChannelTypeManager::class);
            $channelType = $channelTypeManager->create($channelValue);

            // 检查是否实现了获取接待人员的方法
            if (!($channelType instanceof ChannelTypeInterface)) {
                return ['error' => '该渠道类型不支持查看接待人员'];
            }

            // 设置配置
            $config = $record->config ?? [];
            /** @var \HuiZhiDa\Core\Domain\Channel\Strategies\ChannelType\WorkWechatChannelType $channelType */
            if (method_exists($channelType, 'setConfig')) {
                $channelType->setConfig($config);
            }

            // 获取接待人员列表
            $receptionists = $channelType->getReceptionists($applicationId);

            // 转换为数组格式供 RepeatableEntry 使用
            $receptionistsData = array_map(function ($receptionist) {
                return [
                    'type' => $receptionist->type->value,
                    'id' => $receptionist->id,
                    'status' => $receptionist->status->value,
                ];
            }, $receptionists);

            return ['receptionists' => $receptionistsData];
        } catch (\Exception $e) {
            return ['error' => '获取接待人员失败: ' . $e->getMessage()];
        }
    }

    /**
     * 获取添加接待人员的操作
     */
    protected static function getAddReceptionistAction(): Action
    {
        return Action::make('add_receptionist')
            ->label('添加接待人员')
            ->icon('heroicon-o-plus')
            ->color('success')
            ->modalHeading('添加接待人员')
            ->form([
                TextInput::make('memberId')
                    ->label('企业成员ID')
                    ->required()
                    ->maxLength(255)
                    ->helperText('请输入要添加的企业成员ID'),
            ])
            ->action(function ($record, array $arguments, array $data) {
                $applicationId = $arguments['applicationId'] ?? '';
                $applicationName = $arguments['applicationName'] ?? '';
                $memberId = $data['memberId'] ?? '';

                if (!$memberId) {
                    Notification::make()
                        ->title('添加失败')
                        ->body('请选择企业成员')
                        ->danger()
                        ->send();
                    return;
                }

                try {
                    // 获取渠道类型
                    $channelValue = $record->channel instanceof \BackedEnum
                        ? $record->channel->value
                        : (string) $record->channel;

                    if (!$channelValue) {
                        Notification::make()
                            ->title('添加失败')
                            ->body('渠道类型未设置')
                            ->danger()
                            ->send();
                        return;
                    }

                    if (!$applicationId) {
                        Notification::make()
                            ->title('添加失败')
                            ->body('应用ID未提供')
                            ->danger()
                            ->send();
                        return;
                    }

                    // 创建渠道类型实例
                    $channelTypeManager = app(ChannelTypeManager::class);
                    $channelType = $channelTypeManager->create($channelValue);

                    // 检查是否实现了添加接待人员的方法
                    if (!($channelType instanceof ChannelTypeInterface)) {
                        Notification::make()
                            ->title('添加失败')
                            ->body('该渠道类型不支持添加接待人员')
                            ->danger()
                            ->send();
                        return;
                    }

                    // 设置配置
                    $config = $record->config ?? [];
                    /** @var \HuiZhiDa\Core\Domain\Channel\Strategies\ChannelType\WorkWechatChannelType $channelType */
                    if (method_exists($channelType, 'setConfig')) {
                        $channelType->setConfig($config);
                    }

                    // 构建 Receptionist 对象
                    $receptionist = Receptionist::from([
                        'type' => ReceptionistTypeEnum::Member,
                        'id' => $memberId,
                        'status' => ReceptionistStatusEnum::Online,
                    ]);

                    // 添加接待人员
                    $success = $channelType->addReceptionist($applicationId, $receptionist);

                    if ($success) {
                        Notification::make()
                            ->title('添加成功')
                            ->body('接待人员已成功添加，请刷新列表查看更新')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('添加失败')
                            ->body('添加接待人员时发生错误')
                            ->danger()
                            ->send();
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('添加失败')
                        ->body('添加接待人员失败: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * 获取删除接待人员的操作
     */
    protected static function getRemoveReceptionistAction(): Action
    {
        return Action::make('remove_receptionist')
            ->label('删除接待人员')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('确认删除')
            ->modalDescription('确定要删除该接待人员吗？')
            ->modalSubmitActionLabel('确认删除')
            ->modalCancelActionLabel('取消')
            ->action(function ($record, array $arguments) {
                $applicationId = $arguments['applicationId'] ?? '';
                $receptionistType = $arguments['receptionistType'] ?? '';
                $receptionistId = $arguments['receptionistId'] ?? '';
                $receptionistStatus = $arguments['receptionistStatus'] ?? '';
                $applicationName = $arguments['applicationName'] ?? '';

                if (!$receptionistType || !$receptionistId || !$receptionistStatus) {
                    Notification::make()
                        ->title('删除失败')
                        ->body('接待人员数据未提供')
                        ->danger()
                        ->send();
                    return;
                }

                try {
                    // 获取渠道类型
                    $channelValue = $record->channel instanceof \BackedEnum
                        ? $record->channel->value
                        : (string) $record->channel;

                    if (!$channelValue) {
                        Notification::make()
                            ->title('删除失败')
                            ->body('渠道类型未设置')
                            ->danger()
                            ->send();
                        return;
                    }

                    if (!$applicationId) {
                        Notification::make()
                            ->title('删除失败')
                            ->body('应用ID未提供')
                            ->danger()
                            ->send();
                        return;
                    }

                    // 创建渠道类型实例
                    $channelTypeManager = app(ChannelTypeManager::class);
                    $channelType = $channelTypeManager->create($channelValue);

                    // 检查是否实现了删除接待人员的方法
                    if (!($channelType instanceof ChannelTypeInterface)) {
                        Notification::make()
                            ->title('删除失败')
                            ->body('该渠道类型不支持删除接待人员')
                            ->danger()
                            ->send();
                        return;
                    }

                    // 设置配置
                    $config = $record->config ?? [];
                    /** @var \HuiZhiDa\Core\Domain\Channel\Strategies\ChannelType\WorkWechatChannelType $channelType */
                    if (method_exists($channelType, 'setConfig')) {
                        $channelType->setConfig($config);
                    }

                    // 构建 Receptionist 对象
                    $receptionist = Receptionist::from([
                        'type' => ReceptionistTypeEnum::from($receptionistType),
                        'id' => $receptionistId,
                        'status' => ReceptionistStatusEnum::from($receptionistStatus),
                    ]);

                    // 删除接待人员
                    $success = $channelType->removeReceptionist($applicationId, $receptionist);

                    if ($success) {
                        Notification::make()
                            ->title('删除成功')
                            ->body('接待人员已成功删除，请刷新列表查看更新')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('删除失败')
                            ->body('删除接待人员时发生错误')
                            ->danger()
                            ->send();
                    }
                } catch (\Exception $e) {
                    Notification::make()
                        ->title('删除失败')
                        ->body('删除接待人员失败: ' . $e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
