<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ResolveClientTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $selector = trim((string) $request->query('tenant', ''));
        $baseHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = strtolower($request->getHost());
        if ($selector === '' && is_string($baseHost) && $host !== strtolower($baseHost)) {
            $suffix = '.'.strtolower($baseHost);
            if (str_ends_with($host, $suffix)) {
                $selector = substr($host, 0, -strlen($suffix));
            }
        }

        abort_if($selector === '' || $selector === 'www', 404, 'Choose your car wash company to continue.');
        $tenantQuery = Tenant::withoutGlobalScopes()
            ->where('status', 'active')
            ->where(function ($query) use ($selector): void {
                $query->where('email', $selector);
                if (ctype_digit($selector)) {
                    $query->orWhere('id', (int) $selector);
                }
            });
        $tenant = $tenantQuery->first();
        if (! $tenant) {
            $tenant = Tenant::withoutGlobalScopes()
                ->where('status', 'active')
                ->get()
                ->first(fn (Tenant $candidate): bool => Str::slug($candidate->name) === Str::slug($selector));
        }
        abort_unless($tenant, 404, 'Car wash company not found.');
        abort_unless($tenant->hasFeature('client_portal'), 404, 'The client portal is not enabled for this company.');

        $request->attributes->set('clientTenant', $tenant);

        return $next($request);
    }
}
