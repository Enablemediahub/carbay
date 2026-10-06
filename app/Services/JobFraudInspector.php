<?php

namespace App\Services;

use App\Models\CashReconciliation;
use App\Models\FraudFlag;
use App\Models\Job;
use App\Models\Tenant;

class JobFraudInspector
{
    public function inspect(Job $job): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($job->tenant_id);
        if (! $tenant || ! $tenant->hasFeature('fraud_flags')) {
            return;
        }

        $this->flagJob($job, 'no_worker', ! $job->workers()->exists(), 'This job was recorded without an assigned worker.');

        $duplicate = Job::withoutGlobalScopes()
            ->where('tenant_id', $job->tenant_id)
            ->whereKeyNot($job->id)
            ->whereRaw("UPPER(REPLACE(plate, ?, '')) = ?", [' ', str_replace(' ', '', strtoupper($job->plate))])
            ->where('created_at', '>=', $job->created_at->copy()->subHours(2))
            ->exists();
        $this->flagJob($job, 'duplicate_plate', $duplicate, 'This vehicle registration was used by another job within two hours.');

        $grossAmount = (float) $job->total_amount + (float) $job->discount_amount;
        $discountPercent = $grossAmount > 0 ? (float) $job->discount_amount / $grossAmount * 100 : 0;
        $this->flagJob(
            $job,
            'high_discount',
            $discountPercent > (float) $tenant->fraud_discount_threshold_pct,
            'Job discount of '.number_format($discountPercent, 2).'% exceeds the company threshold of '.$tenant->fraud_discount_threshold_pct.'%.',
        );
    }

    public function inspectCashReconciliation(CashReconciliation $reconciliation): void
    {
        $tenant = Tenant::withoutGlobalScopes()->find($reconciliation->tenant_id);
        if (! $tenant || ! $tenant->hasFeature('fraud_flags')) {
            return;
        }

        $difference = abs((float) $reconciliation->expected_amount - (float) $reconciliation->entered_amount);
        $this->flagReconciliation(
            $reconciliation,
            'cash_mismatch',
            $difference > 0.01,
            'Entered cash differs from expected cash by GH₵ '.number_format($difference, 2).'.',
        );
    }

    private function flagJob(Job $job, string $type, bool $condition, string $description): void
    {
        $query = FraudFlag::withoutGlobalScopes()->where('job_id', $job->id)->where('flag_type', $type);
        if (! $condition) {
            $query->where('status', 'open')->delete();

            return;
        }
        if ($query->exists()) {
            return;
        }

        FraudFlag::withoutGlobalScopes()->create([
            'tenant_id' => $job->tenant_id,
            'branch_id' => $job->branch_id,
            'job_id' => $job->id,
            'flag_type' => $type,
            'description' => $description,
            'status' => 'open',
        ]);
    }

    private function flagReconciliation(CashReconciliation $record, string $type, bool $condition, string $description): void
    {
        $query = FraudFlag::withoutGlobalScopes()
            ->where('cash_reconciliation_id', $record->id)
            ->where('flag_type', $type);
        if (! $condition) {
            $query->where('status', 'open')->delete();

            return;
        }
        if ($query->exists()) {
            return;
        }

        FraudFlag::withoutGlobalScopes()->create([
            'tenant_id' => $record->tenant_id,
            'branch_id' => $record->branch_id,
            'cash_reconciliation_id' => $record->id,
            'flag_type' => $type,
            'description' => $description,
            'status' => 'open',
        ]);
    }
}
