<?php

namespace App\Filament\Superadmin\Widgets;

use App\Models\Tenant;
use App\Models\User;
use App\Support\Currency;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class TenantStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $activeTenants = Tenant::query()->where('status', 'active');
        $revenue = (clone $activeTenants)
            ->join('packages', 'packages.id', '=', 'tenants.package_id')
            ->selectRaw("SUM(CASE WHEN packages.billing_cycle = 'yearly' THEN packages.price / 12 ELSE packages.price END) as monthly_revenue")
            ->value('monthly_revenue') ?? 0;
        $smsCreditsUsed = DB::table('sms_logs')
            ->where('credit_refunded', false)
            ->sum('credits_charged');
        $plateScans = DB::table('plate_scans')->count();
        $tenantsByPackage = Tenant::query()
            ->join('packages', 'packages.id', '=', 'tenants.package_id')
            ->selectRaw('packages.name, COUNT(tenants.id) as tenants_count')
            ->groupBy('packages.name')
            ->orderBy('packages.name')
            ->get()
            ->map(fn ($row) => $row->name.': '.$row->tenants_count)
            ->join(' · ');

        return [
            Stat::make('Tenants', Tenant::query()->count()),
            Stat::make('Monthly recurring revenue', Currency::format($revenue)),
            Stat::make('Active users', User::query()->where('status', 'active')->count()),
            Stat::make('Tenants by package', $tenantsByPackage ?: 'No plans assigned'),
            Stat::make('SMS credits used', number_format((int) $smsCreditsUsed)),
            Stat::make('Plate scans', number_format($plateScans)),
        ];
    }
}
