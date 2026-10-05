<?php

namespace App\Filament\Superadmin\Resources\VehicleMakeResource\Pages;

use App\Filament\Superadmin\Resources\VehicleMakeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVehicleMake extends EditRecord
{
    protected static string $resource = VehicleMakeResource::class;

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
