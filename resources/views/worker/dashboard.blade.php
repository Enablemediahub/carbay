<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Your wash activity · Carbay+</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #173c32; background: #f4f5ec; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        header { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 18px max(22px, calc((100vw - 1060px) / 2)); background: #fff; border-bottom: 1px solid #e5e9e1; }
        .brand { font-weight: 800; letter-spacing: -.04em; font-size: 19px; }
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
    <div class="brand">carbay+</div>
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
</body>
</html>
