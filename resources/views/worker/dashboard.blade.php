<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="theme-color" content="#0096FF">
    <link rel="icon" href="{{ asset('carbay-favicon-512.png') }}" type="image/png">
    <title>Your wash activity · Carbay+</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #173c32; background: #f4f5ec; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px max(22px, calc((100vw - 1060px) / 2)); background: #fff; border-bottom: 1px solid #e5e9e1; }
        .brand-logo { display: block; width: auto; max-width: 110px; height: 64px; object-fit: contain; }
        .logout { padding: 10px 15px; color: #155642; border: 1px solid #d8e1d9; border-radius: 9px; background: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        main { width: min(100% - 36px, 1000px); margin: 42px auto; }
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
        .activity { overflow: hidden; }
        .sale { display: flex; justify-content: space-between; gap: 18px; padding: 16px 19px; border-top: 1px solid #edf0eb; }
        .sale:first-child { border-top: 0; }
        .sale strong { display: block; margin-bottom: 5px; }
        .amount { white-space: nowrap; font-weight: 800; }
        .empty { padding: 26px 20px; color: #718078; }
        @media (max-width: 560px) { header { padding: 14px 18px; } main { margin-top: 30px; } .stats { gap: 10px; } .stat { padding: 17px; } .stat strong { font-size: 22px; } .sale { padding: 15px; } }
    </style>
</head>
<body>
<header>
    <img class="brand-logo" src="{{ asset('carbay-logo.png') }}" alt="Carbay+">
    <form method="post" action="{{ route('worker.logout') }}">
        @csrf
        <button class="logout" type="submit">Sign out</button>
    </form>
</header>
<main>
    <div class="welcome">
        <div class="eyebrow">{{ $tenant->name }} · {{ $branch->name }}</div>
        <h1>Good work, {{ $worker->name }}.</h1>
        <div class="muted">Here’s your completed wash activity.</div>
    </div>

    <section class="stats" aria-label="Today's activity">
        <div class="stat"><span>Completed washes today</span><strong>{{ $todayCount }}</strong></div>
        <div class="stat"><span>Sales attributed to you today</span><strong>GH₵ {{ number_format((float) $todayTotal, 2) }}</strong></div>
    </section>

    <section class="stats" aria-label="Wallet earnings">
        <div class="stat"><span>Earned today</span><strong>GH₵ {{ number_format($todayEarnings, 2) }}</strong></div>
        <div class="stat"><span>Earned this week</span><strong>GH₵ {{ number_format($weekEarnings, 2) }}</strong></div>
        <div class="stat"><span>Earned this month</span><strong>GH₵ {{ number_format($monthEarnings, 2) }}</strong></div>
        <div class="stat"><span>Available wallet</span><strong>GH₵ {{ number_format((float) ($wallet?->available_balance ?? 0), 2) }}</strong><span>Pending GH₵ {{ number_format((float) ($wallet?->pending_balance ?? 0), 2) }}</span></div>
    </section>
    <section class="stats" aria-label="Wash count">
        <div class="stat"><span>Cars washed today</span><strong>{{ $todayCount }}</strong></div>
        <div class="stat"><span>Cars washed this week</span><strong>{{ $weekCount }}</strong></div>
        <div class="stat"><span>Cars washed this month</span><strong>{{ $monthCount }}</strong></div>
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
                    <strong>{{ $job->plate }} · {{ ucfirst($job->status) }}</strong>
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
</main>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('service-worker.js') }}'));
    }
</script>
</body>
</html>
