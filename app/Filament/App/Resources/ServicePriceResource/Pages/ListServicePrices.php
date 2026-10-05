<?php

namespace App\Filament\App\Resources\ServicePriceResource\Pages;

use App\Filament\App\Resources\ServicePriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServicePrices extends ListRecords
{
    protected static string $resource = ServicePriceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Activate global service')];
    }
}
