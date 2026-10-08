<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Company subscription — Carbay+</title></head>
<body style="margin:0;min-height:100vh;background:#edf6ff;font-family:system-ui,sans-serif">
    @include('shared.subscription-popup', ['blockedNotice' => true])
    @if (! app(\App\Support\SubscriptionAccess::class)->state($tenant)['attention'])
        <main style="padding:40px"><p>Your company subscription is active.</p><a href="{{ $role === 'worker' ? '/worker' : '/app' }}">Open dashboard</a></main>
    @endif
</body></html>
