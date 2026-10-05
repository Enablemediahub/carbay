<?php

namespace App\Filament\App\Resources\WorkerResource\Pages;

use App\Filament\App\Resources\WorkerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditWorker extends EditRecord
{
    protected static string $resource = WorkerResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
