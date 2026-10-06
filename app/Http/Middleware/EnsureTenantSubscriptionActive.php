<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Tenant;
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

        $inTrial = $tenant->status === 'trial'
            && ($tenant->trial_ends_at === null || $tenant->trial_ends_at->isFuture());
        if (
            $tenant->status === 'active'
            && $user->role === 'manager'
            && ! Branch::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->whereKey($user->branch_id)
                ->where('status', 'active')
                ->exists()
        ) {
            abort(402, 'This branch is inactive. Contact the company owner about its add-on invoice.');
        }
        if ($tenant->status === 'active' || $inTrial) {
            return $next($request);
        }

        if ($user->role === 'ceo' && $request->is('app/subscription')) {
            return $next($request);
        }

        if ($user->role === 'ceo') {
            return redirect('/app/subscription')
                ->with('warning', 'Your subscription needs attention. Review your invoices to restore full access.');
        }

        abort(402, 'Your company subscription is inactive. Ask the company owner to review billing.');
    }
}
