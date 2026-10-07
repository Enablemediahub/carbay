<?php

namespace App\Support;

use App\Models\Job;
use App\Models\WashSale;
use App\Models\Worker;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class DashboardSales
{
    public function platformToday(): float
    {
        return $this->washSales()
            ->whereDate('sold_at', today())
            ->sum('total_amount')
            + $this->jobs()
                ->where('payment_status', 'paid')
                ->where('status', '!=', 'cancelled')
                ->whereDate('created_at', today())
                ->sum('total_amount');
    }

    public function tenantToday(int $tenantId, ?int $branchId = null): float
    {
        return $this->tenantBetween($tenantId, $branchId, today()->startOfDay(), today()->endOfDay());
    }

    public function tenantBetween(
        int $tenantId,
        ?int $branchId,
        CarbonInterface $start,
        CarbonInterface $end,
    ): float {
        return $this->washSales()
            ->where('tenant_id', $tenantId)
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->whereBetween('sold_at', [$start, $end])
            ->sum('total_amount')
            + $this->jobs()
                ->where('tenant_id', $tenantId)
                ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
                ->where('payment_status', 'paid')
                ->where('status', '!=', 'cancelled')
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_amount');
    }

    public function workerToday(Worker $worker): float
    {
        return $this->washSales()
            ->where('tenant_id', $worker->tenant_id)
            ->where('worker_id', $worker->id)
            ->whereDate('sold_at', today())
            ->sum('total_amount')
            + $this->jobs()
                ->where('tenant_id', $worker->tenant_id)
                ->where('status', 'completed')
                ->where('payment_status', 'paid')
                ->whereDate('created_at', today())
                ->whereExists(fn ($query) => $query
                    ->selectRaw('1')
                    ->from('job_workers')
                    ->whereColumn('job_workers.job_id', 'jobs.id')
                    ->where('job_workers.worker_id', $worker->id))
                ->sum('total_amount');
    }

    private function washSales(): Builder
    {
        return WashSale::withoutGlobalScopes()->where('status', 'completed');
    }

    private function jobs(): Builder
    {
        return Job::withoutGlobalScopes();
    }
}
