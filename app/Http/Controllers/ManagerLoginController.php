<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ManagerLoginController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        if (Auth::guard('web')->user()?->role === 'manager') {
            return redirect('/app');
        }

        return response()->view('manager.login', [
            'companies' => Tenant::withoutGlobalScopes()->orderBy('name')->get(['id', 'name']),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer'],
            'phone' => ['required', 'string', 'max:30'],
            'pin' => ['required', 'digits_between:4,12'],
        ]);
        $phone = PhoneNumber::normalize($data['phone']);
        $manager = PhoneNumber::match(User::withoutGlobalScopes()->where('tenant_id', $data['company_id'])
            ->where('role', 'manager')->where('status', 'active'), $phone ?? '')->first();
        $tenant = $manager ? Tenant::withoutGlobalScopes()->find($manager->tenant_id) : null;
        $branchActive = $manager && Branch::withoutGlobalScopes()->where('tenant_id', $manager->tenant_id)
            ->whereKey($manager->branch_id)->where('status', 'active')->exists();

        if (! $manager?->pin || ! $tenant || ! $branchActive || ! Hash::check($data['pin'], $manager->pin)) {
            throw ValidationException::withMessages(['credentials' => 'Those company, phone and PIN details could not be verified.']);
        }

        Auth::guard('web')->login($manager);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect('/app');
    }
}
