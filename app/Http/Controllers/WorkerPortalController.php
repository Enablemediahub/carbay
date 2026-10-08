<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Feature;
use App\Models\Scopes\SaleTenantScope;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Models\WashSale;
use App\Models\Worker;
use App\Services\WalletService;
use App\Support\PhoneNumber;
use App\Support\WorkerSettlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $feature = Feature::query()
            ->where('key', 'worker_pin_login')
            ->where('is_active', true)
            ->first();
        $companies = collect();

        if ($feature) {
            $companies = Tenant::withoutGlobalScopes()
                ->with([
                    'tenantFeatures' => fn ($query) => $query
                        ->withoutGlobalScopes()
                        ->where('feature_id', $feature->id),
                    'package.packageFeatures' => fn ($query) => $query
                        ->where('feature_id', $feature->id),
                ])
                ->get()
                ->filter(function (Tenant $tenant): bool {
                    $override = $tenant->tenantFeatures->first();
                    if ($override) {
                        return $override->enabled;
                    }

                    return (bool) $tenant->package?->packageFeatures->first()?->enabled;
                })
                ->sortBy('name')
                ->values();
        }

        return view('worker.login', ['companies' => $companies]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:tenants,id'],
            'phone' => ['required', 'string', 'max:30'],
            'pin' => ['required', 'digits_between:4,12'],
        ]);

        $tenant = Tenant::withoutGlobalScopes()
            ->whereKey($data['company_id'])
            ->first();

        $data['phone'] = PhoneNumber::normalize($data['phone']);

        $worker = $tenant && $tenant->hasFeature('worker_pin_login')
            ? PhoneNumber::match(Worker::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('status', 'active'), $data['phone'] ?? '')->first()
            : null;

        if (! $worker || ! $worker->pin || ! Hash::check($data['pin'], $worker->pin)) {
            throw ValidationException::withMessages([
                'credentials' => 'Those company, phone and PIN details could not be verified.',
            ]);
        }

        RateLimiter::clear($this->identityKey((int) $data['company_id'], $data['phone']));
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
        $jobQuery = $worker->jobs()->with('services');
        $todaySettlement = app(WorkerSettlement::class)->today($worker);
        $periods = [];
        foreach (['today' => today(), 'week' => now()->startOfWeek(), 'month' => now()->startOfMonth(), 'year' => now()->startOfYear()] as $period => $start) {
            $periods[$period] = [
                'earned' => app(WorkerSettlement::class)->earnedBetween($worker, $start, now()),
                'cars' => (clone $jobQuery)->where('jobs.status', 'completed')->where('jobs.payment_status', 'paid')
                    ->where('jobs.job_type', 'vehicle')->whereBetween('jobs.created_at', [$start, now()])->count(),
            ];
        }

        return view('worker.dashboard', [
            'worker' => $worker,
            'tenant' => Tenant::withoutGlobalScopes()->findOrFail($worker->tenant_id),
            'branch' => Branch::withoutGlobalScopes()
                ->where('tenant_id', $worker->tenant_id)
                ->findOrFail($worker->branch_id),
            'earningPeriods' => $periods,
            'recentJobs' => (clone $jobQuery)->latest('jobs.created_at')->limit(20)->get(),
            'recentSales' => (clone $salesQuery)
                ->with(['items' => fn ($query) => $query->withoutGlobalScope(SaleTenantScope::class)])
                ->where('status', 'completed')
                ->latest('sold_at')
                ->limit(20)
                ->get(),
            'wallet' => $worker->wallet()->withoutGlobalScopes()->first(),
            'todaySettlement' => $todaySettlement,
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

    private function identityKey(int $companyId, string $phone): string
    {
        return 'worker-identity:'.$companyId.'|'.preg_replace('/\D+/', '', PhoneNumber::normalize($phone) ?? '');
    }
}
