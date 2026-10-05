<?php

namespace App\Filament\Superadmin\Resources\FeatureResource\Pages;

use App\Filament\Superadmin\Resources\FeatureResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFeatures extends ListRecords
{
    protected static string $resource = FeatureResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
