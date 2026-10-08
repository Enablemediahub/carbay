<?php

namespace App\Filament\App\Resources\ServicePriceResource\Pages;

use App\Filament\App\Pages\GhanaianPricing;
use App\Filament\App\Resources\ServicePriceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServicePrices extends ListRecords
{
    protected static string $resource = ServicePriceResource::class;

    public function getSubheading(): ?string
    {
        return 'Standard menu: add a service from the shared catalogue, then set your company’s price. Use “Choose service system / Ghanaian menu” to select which menu your company uses.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('ghanaian')->label('Choose service system / Ghanaian menu')->url(GhanaianPricing::getUrl()),
            Actions\CreateAction::make()->label('Add catalogue service to Standard menu'),
        ];
    }
}
