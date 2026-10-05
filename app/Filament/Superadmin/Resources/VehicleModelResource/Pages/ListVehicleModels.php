<?php

namespace App\Filament\Superadmin\Resources\VehicleModelResource\Pages;

use App\Filament\Superadmin\Resources\VehicleModelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVehicleModels extends ListRecords
{
    protected static string $resource = VehicleModelResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
