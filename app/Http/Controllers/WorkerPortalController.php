<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Branch;
use App\Models\Scopes\SaleTenantScope;
use App\Models\Scopes\TenantScope;
use App\Models\Worker;
use App\Models\WashSale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkerPortalController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (session()->has('worker_portal.worker_id')) {
            return redirect()->route('worker.dashboard');
        }

        return view('worker.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'pin' => ['required', 'digits_between:4,12'],
        ]);

        $tenant = Tenant::withoutGlobalScopes()
            ->where('email', $data['company_email'])
            ->where('status', 'active')
            ->first();

        $worker = $tenant && $tenant->hasFeature('worker_pin_login')
            ? Worker::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('phone', $data['phone'])
                ->where('status', 'active')
                ->first()
            : null;

        if (! $worker || ! $worker->pin || ! Hash::check($data['pin'], $worker->pin)) {
            throw ValidationException::withMessages([
                'credentials' => 'Those company, phone and PIN details could not be verified.',
            ]);
        }

        RateLimiter::clear($this->identityKey($data['company_email'], $data['phone']));
        $request->session()->regenerate();
        $request->session()->put('worker_portal', [
            'worker_id' => $worker->id,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->route('worker.dashboard');
    }

    public function dashboard(Request $request): View
    {
        /** @var Worker $worker */
        $worker = $request->attributes->get('workerPortalWorker');
        $salesQuery = WashSale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $worker->tenant_id)
            ->where('worker_id', $worker->id);
        $todaySales = (clone $salesQuery)
            ->where('status', 'completed')
            ->whereDate('sold_at', today());

        return view('worker.dashboard', [
            'worker' => $worker,
            'tenant' => Tenant::withoutGlobalScopes()->findOrFail($worker->tenant_id),
            'branch' => Branch::withoutGlobalScopes()
                ->where('tenant_id', $worker->tenant_id)
                ->findOrFail($worker->branch_id),
            'todayTotal' => (clone $todaySales)->sum('total_amount'),
            'todayCount' => (clone $todaySales)->count(),
            'recentSales' => (clone $salesQuery)
                ->with(['items' => fn ($query) => $query->withoutGlobalScope(SaleTenantScope::class)])
                ->where('status', 'completed')
                ->latest('sold_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('worker_portal');
        $request->session()->regenerateToken();

        return redirect()->route('worker.login');
    }

    private function identityKey(string $companyEmail, string $phone): string
    {
        return 'worker-identity:'.strtolower($companyEmail).'|'.preg_replace('/\D+/', '', $phone);
    }
}
