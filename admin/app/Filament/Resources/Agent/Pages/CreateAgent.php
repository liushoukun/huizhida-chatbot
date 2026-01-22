<?php

namespace App\Filament\Resources\Agent\Pages;

use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Agent\AgentResource;
use RedJasmine\FilamentSupport\Helpers\ResourcePageHelper;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

    use ResourcePageHelper;
}
