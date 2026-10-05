<?php

namespace App\Filament\App\Resources\WorkerResource\Pages;

use App\Filament\App\Resources\WorkerResource;
use App\Models\Tenant;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListWorkers extends ListRecords
{
    protected static string $resource = WorkerResource::class;

    protected function getHeaderActions(): array
    {
        $tenant = Auth::user()?->tenant;
        $limit = $tenant?->workerLimit() ?? 0;
        $active = $tenant?->activeWorkerCount() ?? 0;

        return [
            Actions\CreateAction::make()
                ->disabled($active >= $limit)
                ->tooltip($active >= $limit
                    ? "Worker limit reached ({$active}/{$limit}). Deactivate a worker or upgrade the package."
                    : "{$active} of {$limit} active worker seats are in use."),
        ];
    }
}
