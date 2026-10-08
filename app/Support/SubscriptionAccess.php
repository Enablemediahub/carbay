<?php

namespace App\Support;

use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

class SubscriptionAccess
{
    public function state(Tenant $tenant): array
    {
        $paidUntil = SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'paid')->max('period_ends_at');
        $pending = SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'pending')->orderBy('due_at')->first();
        $trial = $tenant->status === 'trial' && (! $tenant->trial_ends_at || $tenant->trial_ends_at->isFuture());
        $expired = $paidUntil && Carbon::parse($paidUntil)->endOfDay()->isPast();
        $due = $pending && ($pending->due_at ?? $pending->created_at)->lte(now());
        $grace = $pending ? ($pending->due_at ?? $pending->created_at)->copy()->addDays(max(0, (int) config('billing.grace_days', 7))) : null;
        if (! $grace && $expired) {
            $grace = Carbon::parse($paidUntil)->addDay()->startOfDay()->addDays(max(0, (int) config('billing.grace_days', 7)));
        }
        $allowed = $trial || ($tenant->status === 'active' && (! $due && ! $expired || $grace?->isFuture()));

        return ['allowed' => (bool) $allowed, 'attention' => ! $allowed || (bool) $due || (bool) $expired,
            'paid_until' => $paidUntil, 'grace_ends_at' => $grace, 'outstanding' => SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'pending')->sum('amount')];
    }
}
