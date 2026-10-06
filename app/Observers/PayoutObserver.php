<?php

namespace App\Observers;

use App\Models\Payout;
use App\Models\Tenant;
use App\Services\ArkeselService;

class PayoutObserver
{
    public function updated(Payout $payout): void
    {
        if (! $payout->wasChanged('status') || $payout->status !== 'paid') {
            return;
        }

        $worker = $payout->worker()->withoutGlobalScopes()->first();
        if (! $worker?->phone) {
            return;
        }
        $tenant = Tenant::withoutGlobalScopes()->findOrFail($payout->tenant_id);
        app(ArkeselService::class)->send(
            $tenant,
            $worker->phone,
            'Your payout of GH₵ '.number_format((float) $payout->amount, 2).' has been confirmed. '
                .($payout->method === 'momo' && $payout->reference ? 'Reference: '.$payout->reference.'.' : ''),
            'payout_confirmation',
            $payout->branch_id,
            null,
            'payout-confirmation-'.$payout->id,
        );
    }
}
