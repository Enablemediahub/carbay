<?php

namespace App\Filament\Superadmin\Widgets;

use App\Models\Tenant;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TenantStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $activeTenants = Tenant::query()->where('status', 'active');
        $revenue = (clone $activeTenants)
            ->join('packages', 'packages.id', '=', 'tenants.package_id')
            ->sum('packages.price');

        return [
            Stat::make('Tenants', Tenant::query()->count()),
            Stat::make('Monthly revenue', 'GH₵ '.number_format((float) $revenue, 2)),
            Stat::make('Active users', User::query()->where('status', 'active')->count()),
        ];
    }
}
