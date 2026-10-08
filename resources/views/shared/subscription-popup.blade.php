@if ($tenant && in_array($role, ['ceo', 'manager', 'worker'], true))
    @php($subscriptionState = app(\App\Support\SubscriptionAccess::class)->state($tenant))
    @if ($subscriptionState['attention'])
        <style>
            .subscription-overlay { position: fixed; inset: 0; z-index: 100; background: #052746b8; display: grid; place-items: center; padding: 20px; }
            .subscription-message { width: min(440px, 100%); box-sizing: border-box; border-radius: 24px; padding: 28px; background: #fff; color: #073c66; box-shadow: 0 24px 80px #05274655; }
            .subscription-message h2 { margin: 0 0 12px; font-size: 23px; font-weight: 700; }
            .subscription-message p { margin: 12px 0; line-height: 1.6; }
            .subscription-message a, .subscription-message button { display: inline-block; margin: 8px 8px 0 0; padding: 12px 18px; border-radius: 12px; background: #07518e; color: #fff; font-weight: 600; border: 0; cursor: pointer; text-decoration: none; }
            .subscription-message .secondary { background: #eef6ff; color: #07518e; }
            .dark .subscription-message { background: #102a43; color: #f0f7ff; }
        </style>
        <div class="subscription-overlay" role="dialog" aria-modal="true" aria-labelledby="subscription-popup-title">
            <section class="subscription-message">
                <h2 id="subscription-popup-title">Company subscription needs attention</h2>
                <p><strong>{{ $tenant->name }}</strong> has an expired, unpaid or inactive subscription.</p>
                @if ($role === 'ceo')
                    <p>Please settle your subscription with Abidale Group to {{ $subscriptionState['allowed'] ? 'keep' : 'restore' }} access for your company, managers and workers.</p>
                    <a href="/app/subscription">Review invoices &amp; pay</a>
                @else
                    <p>Please contact your company Admin/CEO to renew the subscription. You are not responsible for this payment.</p>
                @endif
                @if ($subscriptionState['allowed'])
                    <p>Your payment grace period ends {{ $subscriptionState['grace_ends_at']?->toDayDateTimeString() }}.</p>
                    <button type="button" class="secondary" onclick="this.closest('.subscription-overlay').remove()">Continue for now</button>
                @elseif (! ($blockedNotice ?? false) && $role === 'ceo')
                    <button type="button" class="secondary" onclick="this.closest('.subscription-overlay').remove()">View billing page</button>
                @else
                    <a class="secondary" href="{{ $role === 'worker' ? '/worker' : '/app' }}">Check access again</a>
                    <form method="POST" action="{{ $role === 'worker' ? route('worker.logout') : '/app/logout' }}">@csrf<button class="secondary" type="submit">Sign out</button></form>
                @endif
            </section>
        </div>
    @endif
@endif
