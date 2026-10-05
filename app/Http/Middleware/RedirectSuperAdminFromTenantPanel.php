<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Symfony\Component\HttpFoundation\Response;

class RedirectSuperAdminFromTenantPanel extends Authenticate
{
    public function handle($request, Closure $next, ...$guards): Response
    {
        if (Filament::auth()->user()?->isSuperAdmin()) {
            return redirect('/superadmin');
        }

        return parent::handle($request, $next, ...$guards);
    }
}
