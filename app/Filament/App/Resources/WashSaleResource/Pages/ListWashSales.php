<?php

namespace App\Filament\App\Resources\WashSaleResource\Pages;

use App\Filament\App\Resources\WashSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListWashSales extends ListRecords
{
    protected static string $resource = WashSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Record sale')];
    }
}
