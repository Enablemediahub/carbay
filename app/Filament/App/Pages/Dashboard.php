<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\BranchSales;
use App\Filament\App\Widgets\SalesOverview;
use App\Filament\App\Widgets\TopServices;
use App\Filament\App\Widgets\TopWorkers;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Business overview';

    public function getTitle(): string
    {
        return auth()->user()?->role === 'manager' ? 'Manager dashboard' : 'Admin / CEO dashboard';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newWashJob')
                ->label('New job')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->url(NewWashJob::getUrl(panel: 'app'))
                ->visible(fn (): bool => in_array(auth()->user()?->role, ['manager', 'ceo'], true)),
        ];
    }

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
