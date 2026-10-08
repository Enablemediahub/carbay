<?php

namespace App\Filament\App\Resources\ManagerResource\Pages;

use App\Filament\App\Resources\ManagerResource;
use App\Services\ManagerAccountService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateManager extends CreateRecord
{
    protected static string $resource = ManagerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(ManagerAccountService::class)->save(auth()->user(), $data);
    }
}
