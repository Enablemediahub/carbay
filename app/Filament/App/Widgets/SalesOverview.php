<?php

namespace App\Filament\App\Widgets;

use App\Models\Branch;
use App\Models\Job;
use App\Models\Worker;
use App\Support\Currency;
use App\Support\DashboardSales;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $user = auth()->user();
        $sales = app(DashboardSales::class);
        $tenantId = (int) $user->tenant_id;
        $branchId = $user->role === 'manager' ? (int) $user->branch_id : null;
        $completedJobs = Job::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->whereBetween('created_at', [today()->startOfDay(), today()->endOfDay()])
            ->count();
        $openJobs = Job::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'open')
            ->count();
        $activeWorkers = Worker::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($branchId !== null, fn ($query) => $query->where('branch_id', $branchId))
            ->where('status', 'active')
            ->count();
        $activeBranches = Branch::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->when($branchId !== null, fn ($query) => $query->whereKey($branchId))
            ->where('status', 'active')
            ->count();

        return [
            Stat::make('This week', Currency::format($sales->tenantBetween(
                $tenantId,
                $branchId,
                now()->startOfWeek(),
                now()->endOfWeek(),
            ))),
            Stat::make('This month', Currency::format($sales->tenantBetween(
                $tenantId,
                $branchId,
                now()->startOfMonth(),
                now()->endOfMonth(),
            ))),
            Stat::make('Jobs completed today', number_format($completedJobs)),
            Stat::make('Open jobs', number_format($openJobs)),
            Stat::make('Active workers', number_format($activeWorkers)),
            Stat::make('Active branches', number_format($activeBranches)),
        ];
    }
}
