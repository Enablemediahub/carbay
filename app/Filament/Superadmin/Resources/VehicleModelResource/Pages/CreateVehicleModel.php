<?php

namespace App\Filament\Superadmin\Resources\VehicleModelResource\Pages;

use App\Filament\Superadmin\Resources\VehicleModelResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleModel extends CreateRecord
{
    protected static string $resource = VehicleModelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_global'] = true;

        return $data;
    }
}
