<?php

namespace HuiZhiDa\Filament\Resources\Channel\Pages;

use Filament\Resources\Pages\CreateRecord;
use HuiZhiDa\Filament\Resources\Channel\ChannelResource;
use RedJasmine\FilamentSupport\Helpers\ResourcePageHelper;

class CreateChannel extends CreateRecord
{
    protected static string $resource = ChannelResource::class;

    use ResourcePageHelper;
}
