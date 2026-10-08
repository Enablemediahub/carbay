<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\Worker;
use App\Support\SubscriptionAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWorkerPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        Auth::shouldUse('worker');
        $worker = Auth::guard('worker')->user();
        if (! $worker) {
            return redirect()->route('worker.login');
        }

        $worker = Worker::withoutGlobalScopes()
            ->whereKey($worker->getKey())
            ->where('status', 'active')
            ->first();
        $tenant = $worker
            ? Tenant::withoutGlobalScopes()->whereKey($worker->tenant_id)->first()
            : null;

        if (! $worker || ! $tenant?->hasFeature('worker_pin_login')) {
            Auth::guard('worker')->logout();

            return redirect()
                ->route('worker.login')
                ->withErrors(['credentials' => 'Worker portal access is no longer available.']);
        }

        Auth::guard('worker')->setUser($worker);

        if (! app(SubscriptionAccess::class)->state($tenant)['allowed']
            && ! $request->routeIs('worker.subscription.notice', 'worker.logout')) {
            return redirect()->route('worker.subscription.notice');
        }

        return $next($request);
    }
}
