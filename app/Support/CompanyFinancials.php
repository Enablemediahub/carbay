<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CompanyFinancials
{
    public function query(?Carbon $start, ?Carbon $end): Builder
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $jobs = DB::table('jobs')->where('status', 'completed')->where('payment_status', 'paid')
            ->when($start, fn ($query) => $query->whereBetween('created_at', [$start, $end]));
        $sales = (clone $jobs)->selectRaw('tenant_id, COUNT(*) as job_count, SUM(total_amount) as job_sales')->groupBy('tenant_id');
        $shares = DB::table('job_services')->joinSub((clone $jobs)->select('id', 'tenant_id'), 'completed_jobs', 'completed_jobs.id', '=', 'job_services.job_id')
            ->selectRaw('completed_jobs.tenant_id, SUM(company_share) as company_share, SUM(worker_share) as worker_share')->groupBy('completed_jobs.tenant_id');
        $legacy = DB::table('wash_sales')->where('status', 'completed')
            ->when($start, fn ($query) => $query->whereBetween('sold_at', [$start, $end]))
            ->selectRaw('tenant_id, SUM(total_amount) as legacy_sales')->groupBy('tenant_id');
        $expenses = DB::table('expenses')->when($start, fn ($query) => $query->whereBetween('spent_at', [$start, $end]))
            ->selectRaw('tenant_id, SUM(amount) as expenses')->groupBy('tenant_id');
        $payouts = DB::table('payouts')->where('status', 'paid')
            ->when($start, fn ($query) => $query->whereBetween('paid_at', [$start, $end]))
            ->selectRaw('tenant_id, SUM(amount) as payouts')->groupBy('tenant_id');
        $wallets = DB::table('wallet_transactions')
            ->selectRaw("tenant_id, SUM(CASE WHEN type = 'credit' AND status IN ('paid', 'pending') THEN amount WHEN type IN ('debit', 'payout') AND status = 'paid' THEN -amount ELSE 0 END) as wallet_owed")
            ->groupBy('tenant_id');
        $pendingSales = DB::table('jobs')->where('status', 'completed')->where('payment_status', '!=', 'paid')
            ->when($start, fn ($query) => $query->whereBetween('created_at', [$start, $end]))
            ->selectRaw('tenant_id, SUM(total_amount) as unpaid_sales')->groupBy('tenant_id');

        return Tenant::withoutGlobalScopes()->select('tenants.id', 'tenants.name', 'tenants.email', 'tenants.status')
            ->leftJoinSub($sales, 'sales', 'sales.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($shares, 'shares', 'shares.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($legacy, 'legacy', 'legacy.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($expenses, 'costs', 'costs.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($payouts, 'payouts', 'payouts.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($wallets, 'wallets', 'wallets.tenant_id', '=', 'tenants.id')
            ->leftJoinSub($pendingSales, 'unpaid', 'unpaid.tenant_id', '=', 'tenants.id')
            ->selectRaw('COALESCE(sales.job_count, 0) as job_count, COALESCE(sales.job_sales, 0) + COALESCE(legacy.legacy_sales, 0) as sales_total, COALESCE(legacy.legacy_sales, 0) as legacy_sales, COALESCE(shares.company_share, 0) as company_share, COALESCE(shares.worker_share, 0) as worker_share, COALESCE(costs.expenses, 0) as expenses, COALESCE(shares.company_share, 0) - COALESCE(costs.expenses, 0) as company_after_expenses, COALESCE(payouts.payouts, 0) as payouts, COALESCE(wallets.wallet_owed, 0) as wallet_owed, COALESCE(unpaid.unpaid_sales, 0) as unpaid_sales');
    }
}
