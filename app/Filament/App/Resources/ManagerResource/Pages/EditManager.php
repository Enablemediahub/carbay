<?php

namespace App\Filament\App\Resources\ManagerResource\Pages;

use App\Filament\App\Resources\ManagerResource;
use App\Services\ManagerAccountService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditManager extends EditRecord
{
    protected static string $resource = ManagerResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ManagerAccountService::class)->save(auth()->user(), $data, $record);
    }
}
