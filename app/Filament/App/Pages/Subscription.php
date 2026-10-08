<?php

namespace App\Filament\App\Pages;

use App\Console\Commands\ProcessSubscriptionBilling;
use App\Models\BranchAddonInvoice;
use App\Models\PlatformSetting;
use App\Models\SmsCreditBalance;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Services\SubscriptionBillingService;
use App\Support\SubscriptionAccess;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Subscription extends Page
{
    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        app(ProcessSubscriptionBilling::class)->processTenant((int) Auth::user()->tenant_id);
    }

    public function checkPayment(int $paymentId): void
    {
        abort_unless(static::canAccess(), 403);
        $payment = SubscriptionPayment::withoutGlobalScopes()->where('tenant_id', Auth::user()->tenant_id)->findOrFail($paymentId);
        $paid = app(SubscriptionBillingService::class)->verify($payment);
        Auth::user()->unsetRelation('tenant');
        Notification::make()->title($paid ? 'Payment confirmed' : 'Payment is not yet confirmed')->send();
    }

    protected static ?string $navigationLabel = 'Subscription';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $navigationGroup = 'Company';

    protected static string $view = 'filament.app.pages.subscription';

    public static function canAccess(): bool
    {
        return Auth::user()?->role === 'ceo' && Auth::user()?->tenant_id !== null;
    }

    protected function getViewData(): array
    {
        $tenant = Auth::user()->tenant;

        return [
            'tenant' => $tenant,
            'package' => $tenant->package,
            'branches' => $tenant->branches()->count(),
            'workers' => $tenant->activeWorkerCount(),
            'smsBalance' => SmsCreditBalance::query()->where('tenant_id', $tenant->id)->value('credits_remaining') ?? 0,
            'subscriptionInvoices' => SubscriptionInvoice::query()->latest()->limit(12)->get(),
            'branchInvoices' => BranchAddonInvoice::query()->latest()->limit(12)->get(),
            'upgradeUrl' => 'mailto:'.config('billing.upgrade_email').'?subject='.rawurlencode('Carbay+ plan upgrade for '.$tenant->name),
            'billingSettings' => PlatformSetting::appearance(),
            'subscriptionState' => app(SubscriptionAccess::class)->state($tenant),
            'subscriptionPayments' => SubscriptionPayment::query()->latest()->limit(20)->get(),
        ];
    }
}
