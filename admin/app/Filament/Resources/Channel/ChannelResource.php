<?php

namespace App\Filament\Resources\Channel;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Channel\Pages\CreateChannel;
use App\Filament\Resources\Channel\Pages\EditChannel;
use App\Filament\Resources\Channel\Pages\ListChannels;
use App\Filament\Resources\Channel\Schemas\ChannelForm;
use App\Filament\Resources\Channel\Tables\ChannelTable;
use HuiZhiDa\Channel\Application\Services\ChannelApplicationService;
use HuiZhiDa\Channel\Domain\Data\ChannelData;
use HuiZhiDa\Channel\Domain\Models\Channel;
use RedJasmine\FilamentSupport\Helpers\HasSystemPermission;
use RedJasmine\FilamentSupport\Helpers\ResourcePageHelper;
use RedJasmine\Support\Foundation\Hook\HasHooks;

class ChannelResource extends Resource
{
    use HasHooks;
    use HasSystemPermission;
    use ResourcePageHelper;

    public static string $hookNamePrefix = 'huizhida.filament.channel.resource';

    protected static ?string $model = Channel::class;
    protected static ?string $service = ChannelApplicationService::class;
    protected static ?string $dataClass = ChannelData::class;
    protected static ?int $navigationSort = 2;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    public static function getModelLabel(): string
    {
        return '渠道';
    }

    public static function getNavigationGroup(): ?string
    {
        return '汇智答';
    }

    public static function form(Schema $schema): Schema
    {
        return ChannelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChannelTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChannels::route('/'),
            'create' => CreateChannel::route('/create'),
            'edit' => EditChannel::route('/{record}/edit'),
        ];
    }
}
