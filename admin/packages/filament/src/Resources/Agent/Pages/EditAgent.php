<?php

namespace HuiZhiDa\Filament\Resources\Agent\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use HuiZhiDa\Filament\Resources\Agent\AgentResource;
use RedJasmine\FilamentSupport\Helpers\ResourcePageHelper;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    use ResourcePageHelper;
}
