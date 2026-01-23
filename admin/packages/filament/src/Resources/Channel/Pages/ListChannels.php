<?php

namespace HuiZhiDa\Filament\Resources\Channel\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use HuiZhiDa\Filament\Resources\Channel\ChannelResource;

class ListChannels extends ListRecords
{
    protected static string $resource = ChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
