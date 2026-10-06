<?php

namespace App\Filament\App\Pages;

use App\Models\Client;
use App\Models\Tenant;
use App\Services\ArkeselService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class PromoBlast extends Page
{
    protected static string $view = 'filament.app.pages.promo-blast';

    protected static ?string $slug = 'promo-blast';

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'SMS promo blast';

    protected static ?string $title = 'SMS promo blast';

    public string $message = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->role === 'ceo'
            && auth()->user()?->tenant?->hasFeature('sms');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    public function send(ArkeselService $sms): void
    {
        abort_unless(static::canAccess(), 403);
        $this->validate(['message' => ['required', 'string', 'max:1000']]);
        $tenant = Tenant::withoutGlobalScopes()->findOrFail(auth()->user()->tenant_id);
        $campaign = 'promo-'.Str::uuid();
        $sent = 0;
        $skipped = 0;

        Client::query()
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('phone')
            ->whereJsonContains('preferences_json->sms_marketing', true)
            ->orderBy('id')
            ->chunkById(100, function ($clients) use ($sms, $tenant, $campaign, &$sent, &$skipped): void {
                foreach ($clients as $client) {
                    $log = $sms->send(
                        $tenant,
                        $client->phone,
                        $this->message,
                        'promo',
                        $client->branch_id,
                        $client->id,
                        $campaign,
                    );
                    $log->status === 'failed' ? $skipped++ : $sent++;
                }
            });

        Notification::make()
            ->title('Promo campaign queued')
            ->body($sent.' queued; '.$skipped.' not sent because SMS is disabled or credits are unavailable.')
            ->success()
            ->send();
        $this->message = '';
    }
}
