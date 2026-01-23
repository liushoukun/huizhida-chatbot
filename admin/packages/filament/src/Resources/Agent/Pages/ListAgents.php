<?php

namespace HuiZhiDa\Filament\Resources\Agent\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use HuiZhiDa\Filament\Resources\Agent\AgentResource;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
