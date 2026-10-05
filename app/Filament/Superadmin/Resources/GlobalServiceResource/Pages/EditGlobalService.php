<?php

namespace App\Filament\Superadmin\Resources\GlobalServiceResource\Pages;

use App\Filament\Superadmin\Resources\GlobalServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGlobalService extends EditRecord
{
    protected static string $resource = GlobalServiceResource::class;

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
