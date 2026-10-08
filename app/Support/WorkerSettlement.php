<?php

namespace App\Support;

use App\Models\Worker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkerSettlement
{
    public function earnedBetween(Worker $worker, Carbon $from, Carbon $to): float
    {
        return (float) DB::table('wallet_transactions as credits')
            ->join('jobs', 'credits.job_id', '=', 'jobs.id')
            ->where('credits.worker_id', $worker->id)->where('credits.tenant_id', $worker->tenant_id)
            ->where('jobs.tenant_id', $worker->tenant_id)->where('credits.type', 'credit')
            ->whereIn('credits.status', ['paid', 'pending'])
            ->where('jobs.status', 'completed')->where('jobs.payment_status', 'paid')
            ->whereBetween('jobs.created_at', [$from, $to])->sum('credits.amount');
    }

    public function today(Worker $worker): array
    {
        $credits = DB::table('wallet_transactions as credits')
            ->join('jobs', 'credits.job_id', '=', 'jobs.id')
            ->where('credits.worker_id', $worker->id)->where('credits.tenant_id', $worker->tenant_id)
            ->where('jobs.tenant_id', $worker->tenant_id)->where('credits.type', 'credit')
            ->whereIn('credits.status', ['paid', 'pending'])->where('jobs.status', '!=', 'cancelled')
            ->orderBy('credits.created_at')->orderBy('credits.id')
            ->select('credits.*', 'jobs.status as job_status', 'jobs.payment_status', 'jobs.created_at as job_date')->get();
        $payouts = DB::table('payouts')->where('worker_id', $worker->id)
            ->where('tenant_id', $worker->tenant_id)->where('status', 'paid')->get();
        $linked = $payouts->whereNotNull('job_worker_id')->groupBy('job_worker_id');
        // Older wallet-wide payouts settle the oldest available earnings first.
        $unallocated = (int) round($payouts->whereNull('job_worker_id')->sum('amount') * 100);
        $earned = $paid = 0;
        $allocations = [];
        foreach ($credits as $credit) {
            $amount = (int) round($credit->amount * 100);
            $settled = min($amount, (int) round(($linked->get($credit->job_worker_id)?->sum('amount') ?? 0) * 100));
            if ($credit->status === 'paid') {
                $extra = min($amount - $settled, $unallocated);
                $settled += $extra;
                $unallocated -= $extra;
            }
            if (substr($credit->job_date, 0, 10) !== today()->toDateString()
                || $credit->job_status !== 'completed' || $credit->payment_status !== 'paid') {
                continue;
            }
            $earned += $amount;
            $paid += $settled;
            if ($amount > $settled && $credit->job_worker_id) {
                $allocations[$credit->job_worker_id] = ($amount - $settled) / 100;
            }
        }

        return ['earned' => $earned / 100, 'paid' => $paid / 100, 'owed' => ($earned - $paid) / 100, 'allocations' => $allocations];
    }
}
