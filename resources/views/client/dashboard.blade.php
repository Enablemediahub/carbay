<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0096FF">
    <link rel="icon" href="{{ asset('carbay-favicon-512.png') }}" type="image/png">
    <title>{{ $tenant->name }} · Client portal</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f4f8fc;color:#142637;font:15px system-ui,sans-serif}header{display:flex;justify-content:space-between;align-items:center;gap:16px;padding:10px max(18px,calc((100vw - 980px)/2));background:#fff;border-bottom:1px solid #e0eaf2}.brand{display:flex;align-items:center;gap:14px;font-weight:700}.brand img{width:84px;height:68px;object-fit:contain}main{max-width:980px;margin:28px auto;padding:0 18px}.muted{color:#5b7081}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,290px),1fr));gap:13px}.card{margin:14px 0;padding:18px;border:1px solid #dce8f1;border-radius:16px;background:#fff;box-shadow:0 10px 28px #133b5b0a}.stat{font-size:25px;font-weight:800}label{display:block;margin:12px 0 5px;font-size:13px;font-weight:650}input:not([type=checkbox]),select,textarea{width:100%;min-height:46px;padding:10px 12px;border:1px solid #ccdbe7;border-radius:9px;font:inherit;color:#142637;background:#fff}input[type=checkbox]{width:20px;height:20px;flex:0 0 20px;accent-color:#0096ff}button,.button{display:inline-block;min-height:46px;padding:11px 15px;border:0;border-radius:9px;background:#0096ff;color:#fff;text-decoration:none;font:inherit;font-weight:700;cursor:pointer}button:hover{background:#0075d4}.line{display:flex;justify-content:space-between;gap:12px;padding:12px 0;border-bottom:1px solid #edf2f6}.notice{padding:12px;background:#e8f5ff;border-radius:10px}.small{font-size:13px}.services{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:8px}.services label{display:flex;align-items:center;gap:10px;min-height:54px;margin:0;padding:9px;border:1px solid #e1eaf1;border-radius:9px}.services small{display:block;color:#5b7081;font-weight:500}.booking-total{margin-top:16px;padding:13px;border-radius:10px;background:#f2f8fc}.booking-total div{display:flex;justify-content:space-between;gap:10px;padding:4px 0}.booking-total .grand-total{margin-top:6px;padding-top:9px;border-top:1px solid #dce8f1;font-size:17px;font-weight:800}.error-list{margin:14px 0;padding:12px 12px 12px 32px;border-radius:10px;color:#9b2929;background:#fff0ef}form.inline{display:inline}button[type=submit]{width:100%;margin-top:14px}@media(max-width:560px){header{padding:8px 14px}.brand{gap:8px}.brand img{width:70px;height:56px}main{margin-top:18px;padding:0 12px}.card{padding:15px}.line{align-items:flex-start}}
    </style>
</head>
<body>
<header><span class="brand"><img src="{{ asset('carbay-logo.png') }}" alt="Carbay+"><span>{{ $tenant->name }}</span></span><form method="post" action="{{ route('client.logout', ['tenant' => $tenant->id]) }}">@csrf<button type="submit" style="width:auto;margin:0">Sign out</button></form></header>
<main>
    <h1>Welcome, {{ $client->name }}</h1>
    <p class="muted">Your bookings, wash history and loyalty rewards.</p>
    @if (session('status'))<p class="notice">{{ session('status') }}</p>@endif
    @if ($errors->any())<ul class="error-list" role="alert">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
    <section class="grid">
        <article class="card"><p class="muted">Loyalty points</p><div class="stat">{{ number_format($client->loyalty_points) }}</div><p class="small muted">@if ($tenant->hasFeature('loyalty')){{ $tenant->loyalty_reward_points }} points redeems GH₵{{ number_format((float) $tenant->loyalty_reward_value, 2) }} from a booking.@else Loyalty rewards are not available.@endif</p></article>
        <article class="card"><p class="muted">Book a wash</p>
            <form method="post" action="{{ route('client.bookings.create', ['tenant' => $tenant->id]) }}">
                @csrf
                <label for="service_type">Visit type</label><select id="service_type" name="service_type" required><option value="bay">Visit the bay</option>@if ($tenant->hasFeature('home_service'))<option value="home">Home service</option>@endif</select>
                <label for="vehicle_category_id">Vehicle category</label><select id="vehicle_category_id" name="vehicle_category_id" required><option value="">Choose vehicle</option>@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                <label>Services</label><div class="services">@foreach ($services as $service)<label><input class="service-choice" type="checkbox" name="service_ids[]" value="{{ $service->id }}" data-default-price="{{ (float) $service->default_price }}"><span>{{ $service->name }}<small data-service-price>GH₵ {{ number_format((float) $service->default_price, 2) }}</small></span></label>@endforeach</div>
                @error('service_ids')<p class="small" style="color:#a12d29">{{ $message }}</p>@enderror
                <label for="requested_for">Preferred time</label><input id="requested_for" name="requested_for" type="datetime-local" min="{{ now()->format('Y-m-d\TH:i') }}" value="{{ old('requested_for') }}" required>
                <label for="address">Home address (home service)</label><input id="address" name="address" maxlength="1000" value="{{ old('address') }}" placeholder="Address and location notes">
                <label for="notes">Notes</label><textarea id="notes" name="notes" rows="2" maxlength="1000">{{ old('notes') }}</textarea>
                @if ($tenant->hasFeature('loyalty'))<label style="display:flex;align-items:center;gap:8px"><input id="redeem_reward" type="checkbox" name="redeem_reward" value="1" @checked(old('redeem_reward')) @disabled($client->loyalty_points < $tenant->loyalty_reward_points)>Apply {{ $tenant->loyalty_reward_points }}-point reward</label>@endif
                <div class="booking-total" aria-live="polite">
                    <div><span>Services subtotal</span><strong id="booking-subtotal">GH₵ 0.00</strong></div>
                    @if ($tenant->hasFeature('loyalty'))<div><span>Reward discount</span><strong id="booking-discount">GH₵ 0.00</strong></div>@endif
                    <div class="grand-total"><span>Estimated total</span><strong id="booking-total">GH₵ 0.00</strong></div>
                </div>
                <button type="submit">Request booking</button>
            </form>
        </article>
    </section>
    <section class="card"><h2>Wash history</h2>
        @forelse ($jobs as $job)<div class="line"><span><strong>{{ $job->plate }}</strong><br><span class="small muted">{{ $job->created_at->format('j M Y') }} · {{ $job->services->pluck('service_name')->join(', ') }}</span></span><span>GH₵ {{ number_format((float) $job->total_amount, 2) }}</span></div>@empty<p class="muted">No completed washes recorded yet.</p>@endforelse
    </section>
    <section class="card"><h2>Bookings</h2>
        @forelse ($bookings as $booking)@php($bookingServices = collect($booking->service_ids)->map(fn ($id) => $serviceNamesById[$id] ?? null)->filter()->join(', '))<div class="line"><span><strong>{{ ucfirst($booking->service_type) }} wash</strong><br><span class="small muted">{{ $bookingServices }} · {{ $booking->requested_for->format('j M Y, g:i a') }} · {{ ucfirst($booking->status) }}</span></span><span>GH₵ {{ number_format(max(0, (float) $booking->estimated_amount - (float) $booking->discount_amount), 2) }}</span></div>@empty<p class="muted">No upcoming booking requests.</p>@endforelse
    </section>
    @if ($tenant->hasFeature('loyalty'))<section class="card"><h2>Loyalty activity</h2>
        @forelse ($loyaltyHistory as $transaction)<div class="line"><span>{{ $transaction->description }}</span><span>{{ $transaction->type === 'earn' ? '+' : '−' }}{{ $transaction->points }} points</span></div>@empty<p class="muted">Points from completed paid washes will appear here.</p>@endforelse
    </section>@endif
</main>
<script>
    const categoryPrices = @js($servicePricesByCategory);
    const categorySelect = document.getElementById('vehicle_category_id');
    const serviceChoices = [...document.querySelectorAll('.service-choice')];
    const rewardChoice = document.getElementById('redeem_reward');
    const rewardPoints = {{ (int) $tenant->loyalty_reward_points }};
    const rewardValue = {{ (float) $tenant->loyalty_reward_value }};
    const clientPoints = {{ (int) $client->loyalty_points }};
    const loyaltyEnabled = {{ $tenant->hasFeature('loyalty') ? 'true' : 'false' }};
    const money = amount => `GH₵ ${Number(amount).toFixed(2)}`;

    function updateBookingEstimate() {
        const categoryId = categorySelect.value;
        let subtotal = 0;
        serviceChoices.forEach(choice => {
            const categoryPrice = categoryPrices[categoryId]?.[choice.value];
            const price = categoryPrice === undefined ? Number(choice.dataset.defaultPrice) : Number(categoryPrice);
            choice.parentElement.querySelector('[data-service-price]').textContent = money(price);
            if (choice.checked) subtotal += price;
        });

        const discount = loyaltyEnabled && rewardChoice?.checked && clientPoints >= rewardPoints
            ? Math.min(subtotal, rewardValue)
            : 0;
        document.getElementById('booking-subtotal').textContent = money(subtotal);
        document.getElementById('booking-total').textContent = money(Math.max(0, subtotal - discount));
        const discountElement = document.getElementById('booking-discount');
        if (discountElement) discountElement.textContent = money(discount);
    }

    categorySelect.addEventListener('change', updateBookingEstimate);
    serviceChoices.forEach(choice => choice.addEventListener('change', updateBookingEstimate));
    rewardChoice?.addEventListener('change', updateBookingEstimate);
    updateBookingEstimate();
</script>
</body>
</html>
