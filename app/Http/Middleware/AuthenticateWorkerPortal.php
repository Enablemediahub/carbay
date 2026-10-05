<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\Worker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateWorkerPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session()->get('worker_portal', []);

        if (! isset($session['worker_id'], $session['tenant_id'])) {
            return redirect()->route('worker.login');
        }

        $tenant = Tenant::withoutGlobalScopes()
            ->whereKey($session['tenant_id'])
            ->where('status', 'active')
            ->first();

        $worker = Worker::withoutGlobalScopes()
            ->whereKey($session['worker_id'])
            ->where('tenant_id', $session['tenant_id'])
            ->where('status', 'active')
            ->first();

        if (! $tenant?->hasFeature('worker_pin_login') || ! $worker) {
            $request->session()->forget('worker_portal');

            return redirect()
                ->route('worker.login')
                ->withErrors(['credentials' => 'Worker portal access is no longer available.']);
        }

        $request->attributes->set('workerPortalWorker', $worker);

        return $next($request);
    }
}
