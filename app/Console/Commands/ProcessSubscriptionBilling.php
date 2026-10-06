<?php

namespace App\Console\Commands;

use App\Models\BranchAddonInvoice;
use App\Models\Branch;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessSubscriptionBilling extends Command
{
    protected $signature = 'carbay:billing-cycle';

    protected $description = 'Generate subscription invoices and apply billing grace periods';

    public function handle(): int
    {
        Tenant::withoutGlobalScopes()
            ->whereIn('status', ['active', 'trial'])
            ->orderBy('id')
            ->each(fn (Tenant $tenant) => $this->processTenant($tenant->id));

        return self::SUCCESS;
    }

    private function processTenant(int $tenantId): void
    {
        DB::transaction(function () use ($tenantId): void {
            $tenant = Tenant::withoutGlobalScopes()->lockForUpdate()->find($tenantId);
            if (! $tenant) {
                return;
            }

            $this->generateInvoiceIfDue($tenant);
            $this->processOverdueInvoices($tenant);
        });
    }

    private function generateInvoiceIfDue(Tenant $tenant): void
    {
        if (SubscriptionInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->exists()) {
            return;
        }

        $package = $tenant->package;
        if (! $package || ! in_array($package->billing_cycle, ['monthly', 'yearly'], true)) {
            return;
        }

        $latest = SubscriptionInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('period_ends_at')
            ->orderByDesc('period_ends_at')
            ->first();
        $anchor = $latest
            ? Carbon::parse($latest->period_ends_at)->addDay()->startOfDay()
            : Carbon::parse($tenant->billing_anchor_at ?? ($tenant->status === 'trial' ? $tenant->trial_ends_at : $tenant->created_at))->startOfDay();
        if ($tenant->status === 'trial' && $tenant->trial_ends_at?->isFuture()) {
            return;
        }

        $start = $anchor->copy();
        $end = $this->periodEnd($start, $package->billing_cycle);
        while ($end->lt(today())) {
            $start = $end->copy()->addDay();
            $end = $this->periodEnd($start, $package->billing_cycle);
        }
        if ($start->gt(today())) {
            return;
        }

        $exists = SubscriptionInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereDate('period_starts_at', $start)
            ->whereDate('period_ends_at', $end)
            ->exists();
        if ($exists) {
            return;
        }

        SubscriptionInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'package_id' => $package->id,
            'invoice_number' => 'SUB-'.now()->format('Ymd').'-'.Str::upper(Str::random(10)),
            'amount' => $package->price,
            'currency' => 'GHS',
            'status' => 'pending',
            'period_starts_at' => $start->toDateString(),
            'period_ends_at' => $end->toDateString(),
            'due_at' => now(),
        ]);
    }

    private function processOverdueInvoices(Tenant $tenant): void
    {
        $graceDays = max(0, (int) config('billing.grace_days', 7));
        $pendingSubscription = SubscriptionInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->orderBy('due_at')
            ->first();

        if ($pendingSubscription) {
            $graceEnds = ($pendingSubscription->due_at ?? $pendingSubscription->created_at)
                ->copy()
                ->addDays($graceDays);
            $tenant->forceFill(['grace_period_ends_at' => $graceEnds])->save();
            if ($graceEnds->isPast()) {
                $tenant->forceFill(['status' => 'suspended'])->save();

                return;
            }
        } else {
            $tenant->forceFill(['grace_period_ends_at' => null])->save();
        }

        $pendingBranchInvoices = BranchAddonInvoice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->get();
        foreach ($pendingBranchInvoices as $invoice) {
            $graceEnds = ($invoice->due_at ?? $invoice->created_at)
                ->copy()
                ->addDays($graceDays);
            if ($graceEnds->isPast()) {
                Branch::withoutGlobalScopes()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($invoice->branch_id)
                    ->update(['status' => 'inactive', 'is_addon_paid' => false]);
            }
        }
    }

    private function periodEnd(Carbon $start, string $cycle): Carbon
    {
        return $cycle === 'yearly'
            ? $start->copy()->addYear()->subDay()
            : $start->copy()->addMonthNoOverflow()->subDay();
    }
}
