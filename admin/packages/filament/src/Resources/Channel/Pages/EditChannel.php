<?php

namespace HuiZhiDa\Filament\Resources\Channel\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use HuiZhiDa\Filament\Resources\Channel\ChannelResource;
use RedJasmine\FilamentSupport\Helpers\ResourcePageHelper;

class EditChannel extends EditRecord
{
    protected static string $resource = ChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    use ResourcePageHelper;
}
