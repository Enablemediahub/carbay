<?php

namespace App\Filament\App\Resources\WorkerResource\Pages;

use App\Filament\App\Resources\WorkerResource;
use App\Support\StaffPhoto;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWorker extends EditRecord
{
    protected static string $resource = WorkerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['photo_path'] = StaffPhoto::store($data['photo_path'] ?? null, $this->record);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
