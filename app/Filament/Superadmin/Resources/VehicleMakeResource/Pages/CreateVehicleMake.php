<?php

namespace App\Filament\Superadmin\Resources\VehicleMakeResource\Pages;

use App\Filament\Superadmin\Resources\VehicleMakeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleMake extends CreateRecord
{
    protected static string $resource = VehicleMakeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_global'] = true;

        return $data;
    }
}
