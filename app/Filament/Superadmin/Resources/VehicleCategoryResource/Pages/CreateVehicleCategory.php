<?php

namespace App\Filament\Superadmin\Resources\VehicleCategoryResource\Pages;

use App\Filament\Superadmin\Resources\VehicleCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVehicleCategory extends CreateRecord
{
    protected static string $resource = VehicleCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_global'] = true;

        return $data;
    }
}
