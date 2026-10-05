<?php

namespace App\Filament\Superadmin\Resources\GlobalServiceResource\Pages;

use App\Filament\Superadmin\Resources\GlobalServiceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGlobalService extends CreateRecord
{
    protected static string $resource = GlobalServiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_global'] = true;
        $data['is_active'] = true;

        return $data;
    }
}
