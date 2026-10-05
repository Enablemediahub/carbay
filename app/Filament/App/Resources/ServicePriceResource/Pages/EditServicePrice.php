<?php

namespace App\Filament\App\Resources\ServicePriceResource\Pages;

use App\Filament\App\Resources\ServicePriceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServicePrice extends EditRecord
{
    protected static string $resource = ServicePriceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
