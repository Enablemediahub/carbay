<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\Job;
use App\Services\ArkeselService;
use App\Services\LoyaltyService;

class JobObserver
{
    public function updated(Job $job): void
    {
        $becameEligible = $job->status === 'completed'
            && $job->payment_status === 'paid'
            && ($job->wasChanged('status') || $job->wasChanged('payment_status'));
        if (! $becameEligible) {
            return;
        }

        app(LoyaltyService::class)->earnForJob($job);

        $client = Client::withoutGlobalScopes()
            ->where('tenant_id', $job->tenant_id)
            ->whereKey($job->client_id)
            ->first();
        if (! $client?->phone) {
            return;
        }

        $tenant = $job->tenant()->withoutGlobalScopes()->firstOrFail();
        $services = $job->services()->pluck('service_name')->join(', ');
        $message = 'Thank you for choosing '.$tenant->name.'! Wash receipt '.$job->plate.': '
            .$services.' — GH₵ '.number_format((float) $job->total_amount, 2).'.';
        app(ArkeselService::class)->send(
            $tenant,
            $client->phone,
            $message,
            'job_receipt',
            $job->branch_id,
            $client->id,
            'job-receipt-'.$job->id,
        );
    }
}
