<?php

namespace App\Filament\App\Resources\WorkerResource\Pages;

use App\Filament\App\Resources\WorkerResource;
use App\Support\StaffPhoto;
use Filament\Resources\Pages\CreateRecord;

class CreateWorker extends CreateRecord
{
    protected static string $resource = WorkerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['photo_path'] = StaffPhoto::store($data['photo_path'] ?? null);

        return $data;
    }
}
