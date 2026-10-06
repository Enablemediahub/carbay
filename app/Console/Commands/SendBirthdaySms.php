<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Tenant;
use App\Services\ArkeselService;
use Illuminate\Console\Command;

class SendBirthdaySms extends Command
{
    protected $signature = 'carbay:sms-birthdays';

    protected $description = 'Queue birthday wishes for opted-in clients';

    public function handle(ArkeselService $sms): int
    {
        Tenant::withoutGlobalScopes()->where('status', 'active')->each(function (Tenant $tenant) use ($sms): void {
            if (! $tenant->hasFeature('sms')) {
                return;
            }

            Client::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereNotNull('birthday')
                ->whereMonth('birthday', today()->month)
                ->whereDay('birthday', today()->day)
                ->whereNotNull('phone')
                ->each(function (Client $client) use ($tenant, $sms): void {
                    $sms->send(
                        $tenant,
                        $client->phone,
                        'Happy birthday, '.$client->name.'! From all of us at '.$tenant->name.', have a wonderful day.',
                        'birthday',
                        $client->branch_id,
                        $client->id,
                        'birthday-'.today()->year.'-'.$client->id,
                    );
                });
        });

        return self::SUCCESS;
    }
}
