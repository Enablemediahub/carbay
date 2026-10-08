<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Tenant;
use App\Support\SubscriptionAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->tenant_id) {
            return $next($request);
        }

        $tenant = Tenant::withoutGlobalScopes()->find($user->tenant_id);
        abort_unless($tenant, 403, 'Company account not found.');

        $state = app(SubscriptionAccess::class)->state($tenant);
        if (
            $state['allowed']
            && $user->role === 'manager'
            && ! Branch::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereKey($user->branch_id)
                ->where('status', 'active')
                ->exists()
        ) {
            return response()->view('shared.branch-notice', [], 402);
        }
        if ($state['allowed']) {
            return $next($request);
        }

        if ($user->role === 'ceo' && $request->is('app/subscription')) {
            return $next($request);
        }

        if ($user->role === 'ceo') {
            return redirect('/app/subscription')
                ->with('warning', 'Your subscription needs attention. Review your invoices to restore full access.');
        }

        return redirect()->route('subscription.notice');
    }
}
