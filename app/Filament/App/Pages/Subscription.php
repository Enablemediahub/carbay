<?php

namespace App\Filament\App\Pages;

use App\Models\BranchAddonInvoice;
use App\Models\SmsCreditBalance;
use App\Models\SubscriptionInvoice;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class Subscription extends Page
{
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
        ];
    }
}
