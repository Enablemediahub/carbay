<?php

namespace App\Filament\Superadmin\Resources\GlobalServiceResource\Pages;

use App\Filament\Superadmin\Resources\GlobalServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGlobalServices extends ListRecords
{
    protected static string $resource = GlobalServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->mutateFormDataUsing(fn (array $data): array => [...$data, 'is_global' => true])];
    }
}
