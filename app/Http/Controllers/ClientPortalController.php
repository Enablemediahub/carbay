<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientBooking;
use App\Models\ClientOtpChallenge;
use App\Models\Job;
use App\Models\LoyaltyTransaction;
use App\Models\Service;
use App\Models\ServicePrice;
use App\Models\Tenant;
use App\Models\VehicleCategory;
use App\Services\ArkeselService;
use App\Services\LoyaltyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientPortalController extends Controller
{
    public function registerForm(Request $request): View
    {
        return view('client.register', ['tenant' => $this->tenant($request)]);
    }

    public function loginForm(Request $request): View
    {
        return view('client.login', ['tenant' => $this->tenant($request)]);
    }

    public function requestOtp(Request $request, ArkeselService $sms): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'purpose' => ['required', 'in:register,login'],
            'name' => ['required_if:purpose,register', 'nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today'],
            'sms_marketing' => ['nullable', 'boolean'],
        ]);
        $phone = $this->normalizePhone($data['phone']);
        $existing = Client::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('phone', $phone)
            ->first();
        if (($data['purpose'] === 'register' && $existing) || ($data['purpose'] === 'login' && ! $existing)) {
            return back()->with('status', 'If the number is eligible, a verification code will be sent shortly.');
        }

        ClientOtpChallenge::query()
            ->where('tenant_id', $tenant->id)
            ->where('phone', $phone)
            ->where('purpose', $data['purpose'])
            ->whereNull('verified_at')
            ->delete();

        $code = (string) random_int(100000, 999999);
        $challenge = ClientOtpChallenge::query()->create([
            'tenant_id' => $tenant->id,
            'phone' => $phone,
            'purpose' => $data['purpose'],
            'code_hash' => Hash::make($code),
            'registration_data' => $data['purpose'] === 'register' ? [
                'name' => trim((string) $data['name']),
                'birthday' => $data['birthday'] ?? null,
                'sms_marketing' => (bool) ($data['sms_marketing'] ?? false),
            ] : null,
            'expires_at' => now()->addMinutes(5),
        ]);
        $smsLog = $sms->send(
            $tenant,
            $phone,
            'Your '.$tenant->name.' verification code is '.$code.'. It expires in 5 minutes.',
            'client_otp',
            $tenant->main_branch_id,
            $existing?->id,
            'client-otp-'.$challenge->id,
        );
        if ($smsLog->status === 'failed') {
            $challenge->delete();

            return back()->withInput()->withErrors([
                'sms' => 'A verification text could not be sent. Contact the company or try again later.',
            ]);
        }

        return redirect()
            ->route('client.verify', ['tenant' => $tenant->id])
            ->with('otp_phone', $phone)
            ->with('otp_purpose', $data['purpose'])
            ->with('status', 'If the number is eligible, a verification code will be sent shortly.');
    }

    public function verifyForm(Request $request): View
    {
        return view('client.verify', [
            'tenant' => $this->tenant($request),
            'phone' => (string) session('otp_phone', ''),
            'purpose' => (string) session('otp_purpose', 'login'),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'purpose' => ['required', 'in:register,login'],
            'code' => ['required', 'digits:6'],
        ]);
        $phone = $this->normalizePhone($data['phone']);

        $client = DB::transaction(function () use ($tenant, $phone, $data): ?Client {
            $challenge = ClientOtpChallenge::query()
                ->where('tenant_id', $tenant->id)
                ->where('phone', $phone)
                ->where('purpose', $data['purpose'])
                ->whereNull('verified_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();
            if (! $challenge || $challenge->expires_at->isPast() || $challenge->attempts >= 5) {
                throw ValidationException::withMessages(['code' => 'This verification code has expired. Request a new code.']);
            }
            if (! Hash::check($data['code'], $challenge->code_hash)) {
                $challenge->increment('attempts');

                return null;
            }

            $challenge->update(['verified_at' => now()]);
            $client = Client::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if ($data['purpose'] === 'register') {
                if ($client) {
                    throw ValidationException::withMessages(['phone' => 'An account already exists for that number.']);
                }
                $branchId = $tenant->main_branch_id
                    ?? Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'active')->value('id');
                if (! $branchId) {
                    throw ValidationException::withMessages(['phone' => 'This company has no active branch for client registration.']);
                }
                $client = Client::withoutGlobalScopes()->create([
                    'tenant_id' => $tenant->id,
                    'branch_id' => $branchId,
                    'name' => $challenge->registration_data['name'],
                    'phone' => $phone,
                    'birthday' => $challenge->registration_data['birthday'] ?? null,
                    'preferences_json' => ['sms_marketing' => (bool) ($challenge->registration_data['sms_marketing'] ?? false)],
                ]);
            }

            if (! $client) {
                throw ValidationException::withMessages(['code' => 'The phone number could not be verified.']);
            }

            return $client;
        });
        if (! $client) {
            throw ValidationException::withMessages(['code' => 'That verification code is not correct.']);
        }

        $request->session()->regenerate();
        $request->session()->put('client_portal', [
            'tenant_id' => $tenant->id,
            'client_id' => $client->id,
        ]);

        return redirect()->route('client.dashboard', ['tenant' => $tenant->id]);
    }

    public function dashboard(Request $request): View
    {
        $tenant = $this->tenant($request);
        $client = $request->attributes->get('clientPortalClient');

        return view('client.dashboard', [
            'tenant' => $tenant,
            'client' => $client,
            'jobs' => Job::withoutGlobalScopes()
                ->with('services')
                ->where('tenant_id', $tenant->id)
                ->where('client_id', $client->id)
                ->latest()
                ->limit(30)
                ->get(),
            'bookings' => ClientBooking::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('client_id', $client->id)
                ->latest('requested_for')
                ->limit(20)
                ->get(),
            'loyaltyHistory' => LoyaltyTransaction::withoutGlobalScopes()
                ->where('tenant_id', $tenant->id)
                ->where('client_id', $client->id)
                ->latest()
                ->limit(20)
                ->get(),
            'categories' => VehicleCategory::query()
                ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
                ->orderBy('name')->get(),
        ]);
    }

    public function book(Request $request, LoyaltyService $loyalty): RedirectResponse
    {
        $tenant = $this->tenant($request);
        /** @var Client $client */
        $client = $request->attributes->get('clientPortalClient');
        $data = $request->validate([
            'service_type' => ['required', 'in:bay,home'],
            'vehicle_category_id' => ['required', 'integer'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'distinct'],
            'requested_for' => ['required', 'date', 'after:now'],
            'address' => ['required_if:service_type,home', 'nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'redeem_reward' => ['nullable', 'boolean'],
        ]);
        if ($data['service_type'] === 'home' && ! $tenant->hasFeature('home_service')) {
            throw ValidationException::withMessages(['service_type' => 'Home service is not available from this company.']);
        }
        $category = VehicleCategory::query()
            ->where(fn ($query) => $query->whereNull('tenant_id')->orWhere('tenant_id', $tenant->id))
            ->findOrFail($data['vehicle_category_id']);
        $services = Service::query()->global()->where('is_active', true)->whereIn('id', $data['service_ids'])->get();
        if ($services->count() !== count($data['service_ids'])) {
            throw ValidationException::withMessages(['service_ids' => 'Choose active services.']);
        }
        $prices = ServicePrice::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('vehicle_category_id', $category->id)
            ->where('is_active', true)
            ->whereIn('service_id', $services->modelKeys())
            ->get()->keyBy('service_id');
        $total = 0.0;
        foreach ($services as $service) {
            $total += (float) ($prices->get($service->id)?->price ?? $service->default_price);
        }

        $branchId = $client->branch_id ?: $tenant->main_branch_id;
        abort_unless($branchId, 422, 'This company has no branch available for bookings.');
        DB::transaction(function () use ($tenant, $client, $branchId, $data, $services, $total, $loyalty): void {
            $booking = ClientBooking::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branchId,
                'client_id' => $client->id,
                'service_type' => $data['service_type'],
                'address' => $data['service_type'] === 'home' ? trim($data['address']) : null,
                'requested_for' => $data['requested_for'],
                'service_ids' => $services->modelKeys(),
                'estimated_amount' => round($total, 2),
                'status' => 'pending',
                'notes' => $data['notes'] ?? null,
            ]);

            if ((bool) ($data['redeem_reward'] ?? false)) {
                $loyalty->redeemForBooking($client, $booking, (int) $tenant->loyalty_reward_points);
            }
        });

        return redirect()->route('client.dashboard', ['tenant' => $tenant->id])
            ->with('status', 'Your wash booking request has been sent.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $tenant = $this->tenant($request);
        $request->session()->forget('client_portal');
        $request->session()->regenerateToken();

        return redirect()->route('client.login', ['tenant' => $tenant->id]);
    }

    private function tenant(Request $request): Tenant
    {
        return $request->attributes->get('clientTenant');
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/[\s().-]+/', '', trim($phone));
        if (! is_string($normalized) || ! preg_match('/^\+?[0-9]{7,15}$/', $normalized)) {
            throw ValidationException::withMessages(['phone' => 'Enter a valid phone number including country code where needed.']);
        }

        return $normalized;
    }
}
