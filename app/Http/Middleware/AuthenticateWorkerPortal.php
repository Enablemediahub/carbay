<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\Worker;
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
            ? Tenant::withoutGlobalScopes()->whereKey($worker->tenant_id)->where('status', 'active')->first()
            : null;

        if (! $worker || ! $tenant?->hasFeature('worker_pin_login')) {
            Auth::guard('worker')->logout();

            return redirect()
                ->route('worker.login')
                ->withErrors(['credentials' => 'Worker portal access is no longer available.']);
        }

        Auth::guard('worker')->setUser($worker);

        return $next($request);
    }
}
