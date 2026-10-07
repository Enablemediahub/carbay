@php
    $user = auth()->user();
    $firstName = $user?->role === 'super_admin'
        ? 'Admin'
        : (filled($user?->name) ? str($user->name)->explode(' ')->first() : 'there');
    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening');
@endphp

<section class="carbay-dashboard-hero carbay-dashboard-hero-superadmin" aria-label="Platform overview">
    <div class="carbay-dashboard-hero-copy">
        <span class="carbay-dashboard-eyebrow">{{ now()->format('l, j F') }} · CARBAY+ OPERATIONS</span>
        <h2>{{ $greeting }}, {{ $firstName }}!</h2>
        <p>Sales across all wash businesses today</p>
        <strong class="carbay-dashboard-sales">{{ \App\Support\Currency::format($todaySales) }}</strong>
        <div class="carbay-dashboard-actions">
            <a class="carbay-dashboard-primary-action" href="{{ \App\Filament\Superadmin\Resources\TenantResource::getUrl(panel: 'superadmin') }}">
                <x-filament::icon icon="heroicon-o-building-office-2" />
                <span>Manage tenants</span>
            </a>
            <a class="carbay-dashboard-secondary-action" href="{{ \App\Filament\Superadmin\Resources\SubscriptionInvoiceResource::getUrl(panel: 'superadmin') }}">
                <x-filament::icon icon="heroicon-o-document-text" />
                <span>Billing</span>
            </a>
        </div>
    </div>
    <div class="carbay-dashboard-orbit" aria-hidden="true">
        <span></span>
        <x-filament::icon icon="heroicon-o-banknotes" />
    </div>
</section>
