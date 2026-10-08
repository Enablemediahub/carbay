<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="{{ route('pwa.manifest', ['workspace' => 'worker'], false) }}">
    <meta name="theme-color" content="#0096FF">
    <link rel="icon" href="{{ \App\Models\PlatformSetting::appearance()->assetUrl('icon-512') }}" type="image/png">
    <title>Your wash activity · Carbay+</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #173c32; background: #f4f5ec; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px max(22px, calc((100vw - 1060px) / 2)); background: #fff; border-bottom: 1px solid #e5e9e1; }
        .brand-logo { display: block; width: auto; max-width: 110px; height: 64px; object-fit: contain; }
        .logout { padding: 10px 15px; color: #155642; border: 1px solid #d8e1d9; border-radius: 9px; background: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        main { width: min(100% - 36px, 1000px); margin: 52px auto; }
        .welcome { margin-bottom: 23px; }
        .eyebrow { color: #76867d; font-size: 12px; font-weight: 800; letter-spacing: .13em; text-transform: uppercase; }
        h1 { margin: 8px 0; font-size: clamp(27px, 5vw, 38px); letter-spacing: -.045em; }
        .muted { color: #718078; }
        .stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; margin: 25px 0 32px; }
        .stat, .activity { border: 1px solid #e4e9e1; border-radius: 17px; background: #fff; box-shadow: 0 12px 32px #173c3208; }
        .stat { padding: 21px; }
        .stat span { color: #75837c; font-size: 13px; }
        .stat strong { display: block; margin-top: 9px; font-size: 26px; }
        h2 { font-size: 20px; }
        .worker-hero { position: relative; display: flex; min-height: 225px; align-items: center; overflow: hidden; padding: 25px; border-radius: 25px; color: #f7fbff; background: radial-gradient(circle at 92% 10%, #70caff66, transparent 145px), linear-gradient(140deg, #07518e, #06355f 72%, #052746); box-shadow: 0 16px 36px #05274626; isolation: isolate; }
        .worker-hero-copy { position: relative; z-index: 1; max-width: 370px; }
        .worker-hero .eyebrow { color: #c6e5fb; }
        .worker-hero h1 { margin: 8px 0 5px; color: #fff; font-size: clamp(25px, 5vw, 34px); }
        .worker-hero p { margin: 0; color: #d8ecfa; font-size: 14px; }
        .worker-hero strong { display: block; margin-top: 6px; font-size: clamp(34px, 8vw, 44px); letter-spacing: -.05em; }
        .worker-hero-art { position: absolute; top: 24px; right: -52px; display: grid; width: 205px; height: 205px; place-items: center; border: 1px solid #e0f2ff38; border-radius: 50%; color: #e0f2ffcc; opacity: .16; pointer-events: none; z-index: 0; }
        .worker-hero-art::before, .worker-hero-art::after { position: absolute; border: 1px solid #e0f2ff26; border-radius: 50%; content: ""; }
        .worker-hero-art::before { inset: 16px; }
        .worker-hero-art::after { inset: 33px; }
        .worker-hero-art svg { width: 56px; height: 56px; stroke-width: 1; }
        .stats { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .earnings-period { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-top: 18px; }
        .earnings-period select { padding: 9px 34px 9px 12px; border: 1px solid #afd9f580; border-radius: 12px; background: #123f64; color: #fff; font: inherit; cursor: pointer; }
        .earnings-period select:focus-visible { outline: 2px solid #b9e4ff; outline-offset: 3px; }
        .payment-split { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 12px; }
        .payment-split strong { font-size: 22px; }
        .stat { min-height: 120px; border-radius: 19px; }
        .activity { overflow: hidden; }
        .sale { display: flex; justify-content: space-between; gap: 18px; padding: 16px 19px; border-top: 1px solid #edf0eb; }
        .sale:first-child { border-top: 0; }
        .sale strong { display: block; margin-bottom: 5px; }
        .amount { white-space: nowrap; font-weight: 800; }
        .empty { padding: 26px 20px; color: #718078; }
        @media (max-width: 560px) { header { padding: 14px 18px; } main { margin-top: 42px; } .stats { gap: 10px; grid-template-columns: repeat(2, minmax(0, 1fr)); } .payment-card { grid-column: 1 / -1; } .stat { padding: 17px; } .stat strong { font-size: 22px; } .sale { padding: 15px; } }
    </style>
</head>
<body>
<header>
    <img class="brand-logo" src="{{ \App\Models\PlatformSetting::appearance()->assetUrl('logo') }}" alt="Carbay+">
    <form method="post" action="{{ route('worker.logout') }}">
        @csrf
        <button class="logout" type="submit">Sign out</button>
    </form>
</header>
<main>
    <div class="welcome">
        <section class="worker-hero" aria-label="Your earnings" style="{{ \App\Models\PlatformSetting::appearance()->heroStyle() }}">
            <div class="worker-hero-copy">
                <div class="eyebrow">{{ $tenant->name }} · {{ $branch->name }}</div>
                <h1>Good work, {{ $worker->name }}.</h1>
                @if ($worker->photo_url)
                    <img src="{{ $worker->photo_url }}" alt="{{ $worker->name }}" style="width:64px;height:64px;border-radius:50%;object-fit:cover;margin:12px 0;">
                @endif
                <div class="earnings-period">
                    <label for="earnings-period">Your earnings</label>
                    <select id="earnings-period">
                        <option value="today" selected>Today</option>
                        <option value="week">This week</option>
                        <option value="month">This month</option>
                        <option value="year">This year</option>
                    </select>
                </div>
                <strong id="hero-earnings" aria-live="polite">GH₵ {{ number_format($earningPeriods['today']['earned'], 2) }}</strong>
            </div>
            <div class="worker-hero-art" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m4-9.5a4 4 0 0 0-4-2.5c-2.2 0-4 1.3-4 3s1.8 3 4 3 4 1.3 4 3-1.8 3-4 3a4 4 0 0 1-4-2.5"/></svg>
            </div>
        </section>
    </div>

    <section class="stats" aria-label="Wallet earnings">
        <div class="stat payment-card">
            <div class="payment-split">
                <div><span>Paid from today's earnings</span><strong>GH₵ {{ number_format($todaySettlement['paid'], 2) }}</strong></div>
                <div><span>Still owed for today</span><strong>GH₵ {{ number_format($todaySettlement['owed'], 2) }}</strong></div>
            </div>
            <span>{{ $todaySettlement['earned'] > 0 && $todaySettlement['owed'] == 0 ? 'Fully paid for today' : 'From completed, paid jobs' }}</span>
        </div>
        <div class="stat"><span>Available wallet</span><strong>GH₵ {{ number_format((float) ($wallet?->available_balance ?? 0), 2) }}</strong><span>Pending GH₵ {{ number_format((float) ($wallet?->pending_balance ?? 0), 2) }}</span></div>
        <div class="stat"><span id="cars-period-label">Cars washed today</span><strong id="cars-period-count" aria-live="polite">{{ $earningPeriods['today']['cars'] }}</strong></div>
    </section>

    @if (session('status'))
        <p role="status" class="activity" style="padding:14px 18px;color:#155642">{{ session('status') }}</p>
    @endif

    <section class="activity" style="padding:20px;margin-bottom:28px">
        <h2 style="margin-top:0">Request a payout</h2>
        <form method="post" action="{{ route('worker.payouts.request') }}" style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(150px,1fr))">
            @csrf
            <label>Amount (GH₵)<input name="amount" type="number" min="0.01" step="0.01" max="{{ $wallet?->available_balance ?? 0 }}" required style="display:block;width:100%;padding:11px;border:1px solid #d7ded8;border-radius:9px"></label>
            <label>Payment method<select name="method" required style="display:block;width:100%;padding:11px;border:1px solid #d7ded8;border-radius:9px"><option value="cash">Cash</option><option value="momo">MoMo</option></select></label>
            <label>MoMo reference (if sent)<input name="reference" maxlength="255" style="display:block;width:100%;padding:11px;border:1px solid #d7ded8;border-radius:9px"></label>
            <button class="logout" type="submit" style="align-self:end;background:#155642;color:#fff">Request payout</button>
        </form>
        @if ($errors->any())<p role="alert" style="color:#932e28">{{ $errors->first() }}</p>@endif
    </section>

    <h2>Wallet activity</h2>
    <section class="activity" aria-label="Wallet activity">
        @forelse ($walletHistory as $transaction)
            <article class="sale">
                <div><strong>{{ ucfirst($transaction->type) }} · {{ $transaction->status }}</strong><span class="muted">{{ $transaction->created_at->format('D, j M · g:i a') }}</span></div>
                <div class="amount">GH₵ {{ number_format((float) $transaction->amount, 2) }}</div>
            </article>
        @empty
            <div class="empty">Your job earnings and payouts will appear here.</div>
        @endforelse
    </section>

    <h2>Assigned wash jobs</h2>
    <section class="activity" aria-label="Assigned wash jobs">
        @forelse ($recentJobs as $job)
            <article class="sale">
                <div>
                    <strong>{{ $job->plate ?: 'Standalone cleaning' }} · {{ ucfirst($job->status) }}</strong>
                    <span class="muted">Company-set worker pool: {{ $job->services->map(fn ($item) => $item->service_name.' '.number_format((float) $item->worker_pct, 0).'%')->join(', ') }}. Shared equally by the assigned team.</span>
                    <span class="muted">{{ $job->created_at->format('D, j M · g:i a') }} · {{ $job->services->pluck('service_name')->join(', ') }}</span>
                </div>
                <div class="amount">GH₵ {{ number_format((float) $job->pivot->share_amount, 2) }}</div>
            </article>
        @empty
            <div class="empty">No wash jobs have been assigned to you yet.</div>
        @endforelse
    </section>

    <h2>Recent wash activity</h2>
    <section class="activity" aria-label="Recent completed washes">
        @forelse ($recentSales as $sale)
            <article class="sale">
                <div>
                    <strong>{{ $sale->reference ?: 'Wash sale' }}</strong>
                    <span class="muted">{{ $sale->sold_at->format('D, j M · g:i a') }} · {{ $sale->items->pluck('service_name')->join(', ') }}</span>
                </div>
                <div class="amount">GH₵ {{ number_format((float) $sale->total_amount, 2) }}</div>
            </article>
        @empty
            <div class="empty">No completed washes have been assigned to you yet.</div>
        @endforelse
    </section>
    @include('shared.developer-footer')
</main>
<script>
    const earningPeriods = @json($earningPeriods);
    const periodLabels = { today: 'today', week: 'this week', month: 'this month', year: 'this year' };
    document.getElementById('earnings-period').addEventListener('change', (event) => {
        const period = event.target.value;
        const totals = earningPeriods[period];
        if (!totals) return;
        document.getElementById('hero-earnings').textContent = 'GH₵ ' + Number(totals.earned).toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        document.getElementById('cars-period-label').textContent = 'Cars washed ' + periodLabels[period];
        document.getElementById('cars-period-count').textContent = totals.cars;
    });
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('service-worker.js') }}'));
    }
</script>
@include('shared.subscription-popup', ['role' => 'worker'])
</body>
</html>
