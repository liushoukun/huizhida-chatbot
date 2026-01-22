<?php

namespace App\Filament\Resources\Agent;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use App\Filament\Resources\Agent\Pages\CreateAgent;
use App\Filament\Resources\Agent\Pages\EditAgent;
use App\Filament\Resources\Agent\Pages\ListAgents;
use App\Filament\Resources\Agent\Schemas\AgentForm;
use App\Filament\Resources\Agent\Tables\AgentTable;
use HuiZhiDa\Agent\Application\Services\AgentApplicationService;
use HuiZhiDa\Agent\Domain\Data\AgentData;
use HuiZhiDa\Agent\Domain\Models\Agent;
use RedJasmine\FilamentSupport\Helpers\HasSystemPermission;
use RedJasmine\FilamentSupport\Helpers\ResourcePageHelper;
use RedJasmine\Support\Foundation\Hook\HasHooks;

class AgentResource extends Resource
{
    use HasHooks;
    use HasSystemPermission;
    use ResourcePageHelper;

    public static string $hookNamePrefix = 'huizhida.filament.agent.resource';

    protected static ?string $model = Agent::class;
    protected static ?string $service = AgentApplicationService::class;
    protected static ?string $dataClass = AgentData::class;
    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    public static function getModelLabel(): string
    {
        return '智能体';
    }

    public static function getNavigationGroup(): ?string
    {
        return '汇智答';
    }

    public static function form(Schema $schema): Schema
    {
        return AgentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AgentTable::configure($table);
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
            'index' => ListAgents::route('/'),
            'create' => CreateAgent::route('/create'),
            'edit' => EditAgent::route('/{record}/edit'),
        ];
    }
}
