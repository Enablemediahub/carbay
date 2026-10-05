<?php

namespace App\Filament\Superadmin\Resources\VehicleMakeResource\Pages;

use App\Filament\Superadmin\Resources\VehicleMakeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVehicleMakes extends ListRecords
{
    protected static string $resource = VehicleMakeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
