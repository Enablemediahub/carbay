<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#155642">
    <title>{{ $tenant->name }} · Client portal</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f5ec;color:#173c32;font:15px system-ui,sans-serif}header{display:flex;justify-content:space-between;align-items:center;padding:15px max(18px,calc((100vw - 980px)/2));background:#fff;border-bottom:1px solid #e3e8e1}main{max-width:980px;margin:28px auto;padding:0 18px}.brand{font-weight:800}.muted{color:#6c7972}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:13px}.card{margin:14px 0;padding:18px;border:1px solid #e2e8e1;border-radius:16px;background:#fff}.stat{font-size:25px;font-weight:800}label{display:block;margin:12px 0 5px;font-size:13px;font-weight:650}input,select,textarea{width:100%;padding:10px;border:1px solid #d6ddd6;border-radius:9px;font:inherit}input[type=checkbox]{width:auto}button,.button{display:inline-block;padding:11px 14px;border:0;border-radius:9px;background:#155642;color:white;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}.line{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #edf0eb}.notice{padding:12px;background:#edf6ed;border-radius:10px}.small{font-size:13px}.services{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px}.services label{display:flex;align-items:center;gap:8px;padding:9px;border:1px solid #e1e7e1;border-radius:9px}form.inline{display:inline}
    </style>
</head>
<body>
<header><span class="brand">{{ $tenant->name }}</span><form method="post" action="{{ route('client.logout', ['tenant' => $tenant->id]) }}">@csrf<button type="submit">Sign out</button></form></header>
<main>
    <h1>Welcome, {{ $client->name }}</h1>
    <p class="muted">Your bookings, wash history and loyalty rewards.</p>
    @if (session('status'))<p class="notice">{{ session('status') }}</p>@endif
    <section class="grid">
        <article class="card"><p class="muted">Loyalty points</p><div class="stat">{{ number_format($client->loyalty_points) }}</div><p class="small muted">@if ($tenant->hasFeature('loyalty')){{ $tenant->loyalty_reward_points }} points redeems GH₵{{ number_format((float) $tenant->loyalty_reward_value, 2) }} from a booking.@else Loyalty rewards are not available.@endif</p></article>
        <article class="card"><p class="muted">Book a wash</p>
            <form method="post" action="{{ route('client.bookings.create', ['tenant' => $tenant->id]) }}">
                @csrf
                <label for="service_type">Visit type</label><select id="service_type" name="service_type" required><option value="bay">Visit the bay</option>@if ($tenant->hasFeature('home_service'))<option value="home">Home service</option>@endif</select>
                <label for="vehicle_category_id">Vehicle category</label><select id="vehicle_category_id" name="vehicle_category_id" required><option value="">Choose vehicle</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                <label>Services</label><div class="services">@foreach (\App\Models\Service::query()->global()->where('is_active', true)->orderBy('name')->get() as $service)<label><input type="checkbox" name="service_ids[]" value="{{ $service->id }}">{{ $service->name }} · GH₵ {{ number_format((float) $service->default_price, 2) }}</label>@endforeach</div>
                <label for="requested_for">Preferred time</label><input id="requested_for" name="requested_for" type="datetime-local" min="{{ now()->format('Y-m-d\TH:i') }}" required>
                <label for="address">Home address (home service)</label><input id="address" name="address" maxlength="1000" placeholder="Address and location notes">
                <label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="1000"></textarea>
                @if ($tenant->hasFeature('loyalty'))<label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="redeem_reward" value="1" @disabled($client->loyalty_points < $tenant->loyalty_reward_points)>Apply {{ $tenant->loyalty_reward_points }}-point reward</label>@endif
                @error('service_ids')<p class="small" style="color:#a12d29">{{ $message }}</p>@enderror
                <button type="submit">Request booking</button>
            </form>
        </article>
    </section>
    <section class="card"><h2>Wash history</h2>
        @forelse ($jobs as $job)<div class="line"><span><strong>{{ $job->plate }}</strong><br><span class="small muted">{{ $job->created_at->format('j M Y') }} · {{ $job->services->pluck('service_name')->join(', ') }}</span></span><span>GH₵ {{ number_format((float) $job->total_amount, 2) }}</span></div>@empty<p class="muted">No completed washes recorded yet.</p>@endforelse
    </section>
    <section class="card"><h2>Bookings</h2>
        @forelse ($bookings as $booking)<div class="line"><span>{{ ucfirst($booking->service_type) }} wash<br><span class="small muted">{{ $booking->requested_for->format('j M Y, g:i a') }} · {{ $booking->status }}</span></span><span>GH₵ {{ number_format(max(0, (float) $booking->estimated_amount - (float) $booking->discount_amount), 2) }}</span></div>@empty<p class="muted">No upcoming booking requests.</p>@endforelse
    </section>
    @if ($tenant->hasFeature('loyalty'))<section class="card"><h2>Loyalty activity</h2>
        @forelse ($loyaltyHistory as $transaction)<div class="line"><span>{{ $transaction->description }}</span><span>{{ $transaction->type === 'earn' ? '+' : '−' }}{{ $transaction->points }} points</span></div>@empty<p class="muted">Points from completed paid washes will appear here.</p>@endforelse
    </section>@endif
</main>
</body>
</html>
