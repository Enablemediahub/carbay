<?php

namespace App\Filament\Superadmin\Resources\VehicleCategoryResource\Pages;

use App\Filament\Superadmin\Resources\VehicleCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVehicleCategory extends EditRecord
{
    protected static string $resource = VehicleCategoryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['is_global'] = true;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
