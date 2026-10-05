<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\BranchSales;
use App\Filament\App\Widgets\SalesOverview;
use App\Filament\App\Widgets\TopServices;
use App\Filament\App\Widgets\TopWorkers;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Business overview';

    public function getWidgets(): array
    {
        return [
            SalesOverview::class,
            BranchSales::class,
            TopWorkers::class,
            TopServices::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return ['md' => 2, 'xl' => 2];
    }
}
