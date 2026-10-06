<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Models\Tenant;
use App\Services\ArkeselService;
use Illuminate\Console\Command;

class SendWashReminders extends Command
{
    protected $signature = 'carbay:sms-wash-reminders';

    protected $description = 'Send a reminder 30 days after a client’s last completed wash';

    public function handle(ArkeselService $sms): int
    {
        $from = today()->subDays(31);
        $through = today()->subDays(30)->endOfDay();

        Job::withoutGlobalScopes()
            ->with(['client', 'tenant'])
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$from->startOfDay(), $through])
            ->whereNotNull('client_id')
            ->chunkById(200, function ($jobs) use ($sms): void {
                foreach ($jobs as $job) {
                    $client = $job->client;
                    $tenant = Tenant::withoutGlobalScopes()->find($job->tenant_id);
                    if (! $client?->phone || ! $tenant?->hasFeature('sms')) {
                        continue;
                    }

                    $laterWash = Job::withoutGlobalScopes()
                        ->where('tenant_id', $job->tenant_id)
                        ->where('client_id', $client->id)
                        ->where('status', 'completed')
                        ->where('created_at', '>', $job->created_at)
                        ->exists();
                    if ($laterWash) {
                        continue;
                    }

                    $sms->send(
                        $tenant,
                        $client->phone,
                        'It has been a month since your last wash at '.$tenant->name.'. We would love to see you again!',
                        'wash_reminder',
                        $job->branch_id,
                        $client->id,
                        'wash-reminder-'.$job->id,
                    );
                }
            });

        return self::SUCCESS;
    }
}
