<?php

namespace App\Filament\Superadmin\Resources\VehicleCategoryResource\Pages;

use App\Filament\Superadmin\Resources\VehicleCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVehicleCategories extends ListRecords
{
    protected static string $resource = VehicleCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->mutateFormDataUsing(fn (array $data): array => [...$data, 'is_global' => true])];
    }
}
