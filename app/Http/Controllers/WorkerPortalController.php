<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Scopes\SaleTenantScope;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\WashSale;
use App\Models\Worker;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WorkerPortalController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (Auth::guard('worker')->check()) {
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
        Auth::guard('worker')->login($worker);
        $request->session()->regenerate();

        return redirect()->route('worker.dashboard');
    }

    public function dashboard(Request $request): View
    {
        /** @var Worker $worker */
        $worker = Auth::guard('worker')->user();
        $salesQuery = WashSale::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $worker->tenant_id)
            ->where('worker_id', $worker->id);
        $todaySales = (clone $salesQuery)
            ->where('status', 'completed')
            ->whereDate('sold_at', today());
        $jobQuery = $worker->jobs()->with('services');
        $todayJobs = (clone $jobQuery)
            ->where('jobs.status', 'completed')
            ->whereDate('jobs.created_at', today());
        $weekSales = (clone $salesQuery)
            ->where('status', 'completed')
            ->whereBetween('sold_at', [now()->startOfWeek(), now()->endOfWeek()]);
        $monthSales = (clone $salesQuery)
            ->where('status', 'completed')
            ->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()]);

        return view('worker.dashboard', [
            'worker' => $worker,
            'tenant' => Tenant::withoutGlobalScopes()->findOrFail($worker->tenant_id),
            'branch' => Branch::withoutGlobalScopes()
                ->where('tenant_id', $worker->tenant_id)
                ->findOrFail($worker->branch_id),
            'todayTotal' => (float) (clone $todaySales)->sum('total_amount') + (float) (clone $todayJobs)->sum('jobs.total_amount'),
            'todayCount' => (clone $todaySales)->count() + (clone $todayJobs)->count(),
            'weekCount' => (clone $jobQuery)->where('jobs.status', 'completed')->whereBetween('jobs.created_at', [now()->startOfWeek(), now()->endOfWeek()])->count()
                + $weekSales->count(),
            'monthCount' => (clone $jobQuery)->where('jobs.status', 'completed')->whereBetween('jobs.created_at', [now()->startOfMonth(), now()->endOfMonth()])->count()
                + $monthSales->count(),
            'recentJobs' => (clone $jobQuery)->latest('jobs.created_at')->limit(20)->get(),
            'recentSales' => (clone $salesQuery)
                ->with(['items' => fn ($query) => $query->withoutGlobalScope(SaleTenantScope::class)])
                ->where('status', 'completed')
                ->latest('sold_at')
                ->limit(20)
                ->get(),
            'wallet' => $worker->wallet()->withoutGlobalScopes()->first(),
            'todayEarnings' => $this->earnings($worker, today()->startOfDay(), now()),
            'weekEarnings' => $this->earnings($worker, now()->startOfWeek(), now()),
            'monthEarnings' => $this->earnings($worker, now()->startOfMonth(), now()),
            'walletHistory' => $worker->wallet?->transactions()->withoutGlobalScopes()->latest()->limit(20)->get() ?? collect(),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('worker')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('worker.login');
    }

    public function requestPayout(Request $request, WalletService $walletService): RedirectResponse
    {
        /** @var Worker|null $worker */
        $worker = Auth::guard('worker')->user();
        abort_unless($worker, 403);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'method' => ['required', 'in:cash,momo'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);
        $walletService->payout($worker, (float) $data['amount'], $data['method'], trim((string) ($data['reference'] ?? '')));

        return redirect()->route('worker.dashboard')->with('status', 'Payout request sent to your manager.');
    }

    private function identityKey(string $companyEmail, string $phone): string
    {
        return 'worker-identity:'.strtolower($companyEmail).'|'.preg_replace('/\D+/', '', $phone);
    }

    private function earnings(Worker $worker, Carbon $from, Carbon $to): float
    {
        return (float) $worker->wallet?->transactions()->withoutGlobalScopes()
            ->where('type', 'credit')
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');
    }
}
