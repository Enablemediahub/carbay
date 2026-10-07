@php
    $user = auth()->user();
    $firstName = filled($user?->name) ? str($user->name)->explode(' ')->first() : 'there';
    $greeting = now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening');
    $dashboardScope = match ($user?->role) {
        'manager' => $user?->branch?->name ?? 'Your branch',
        'ceo' => $user?->tenant?->name ?? 'Your business',
        default => 'Your team',
    };
@endphp

<section class="carbay-dashboard-hero carbay-dashboard-hero-manager" aria-label="Welcome">
    <div class="carbay-dashboard-hero-copy">
        <span class="carbay-dashboard-eyebrow">{{ now()->format('l, j F') }} · {{ $dashboardScope }}</span>
        <h2>{{ $greeting }}, {{ $firstName }}!</h2>
        <p>Sales collected today</p>
        <strong class="carbay-dashboard-sales">{{ \App\Support\Currency::format($todaySales) }}</strong>
        <div class="carbay-dashboard-actions">
            @if (in_array($user?->role, ['manager', 'ceo'], true))
                <a class="carbay-dashboard-primary-action" href="{{ \App\Filament\App\Pages\NewWashJob::getUrl(panel: 'app') }}">
                    <x-filament::icon icon="heroicon-o-plus" />
                    <span>Start a wash job</span>
                </a>
            @endif
            <a class="carbay-dashboard-secondary-action" href="{{ \App\Filament\App\Pages\TodayJobs::getUrl(panel: 'app') }}">
                <x-filament::icon icon="heroicon-o-clipboard-document-list" />
                <span>Today's jobs</span>
            </a>
        </div>
    </div>
    <div class="carbay-dashboard-orbit" aria-hidden="true">
        <span></span>
        <x-filament::icon icon="heroicon-o-banknotes" />
    </div>
</section>
