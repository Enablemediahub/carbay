<?php

namespace App\Filament\Superadmin\Resources\VehicleModelResource\Pages;

use App\Filament\Superadmin\Resources\VehicleModelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVehicleModel extends EditRecord
{
    protected static string $resource = VehicleModelResource::class;

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
