<?php

namespace App\Http\Middleware;

use App\Models\Client;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateClientPortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('clientTenant');
        $session = $request->session()->get('client_portal', []);
        if (! $tenant || ($session['tenant_id'] ?? null) !== $tenant->id || ! isset($session['client_id'])) {
            $request->session()->forget('client_portal');

            return redirect()->route('client.login', ['tenant' => $tenant?->id]);
        }

        $client = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereKey($session['client_id'])
            ->first();
        if (! $client) {
            $request->session()->forget('client_portal');

            return redirect()->route('client.login', ['tenant' => $tenant->id]);
        }

        $request->attributes->set('clientPortalClient', $client);

        return $next($request);
    }
}
