<?php

use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\ManagerLoginController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\PlateOcrController;
use App\Http\Controllers\PlatformBrandingController;
use App\Http\Controllers\StaffPhotoController;
use App\Http\Controllers\SubscriptionPaymentController;
use App\Http\Controllers\WorkerPortalController;
use App\Http\Middleware\AuthenticateClientPortal;
use App\Http\Middleware\AuthenticateWorkerPortal;
use App\Http\Middleware\EnsureTenantSubscriptionActive;
use App\Http\Middleware\ResolveClientTenant;
use App\Models\Tenant;
use App\Models\User;
use App\Support\LegalDocuments;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('home');
Route::get('/agreement', fn () => view('legal.page', ['document' => LegalDocuments::get('agreement')]))->name('legal.agreement');
Route::get('/privacy', fn () => view('legal.page', ['document' => LegalDocuments::get('privacy')]))->name('legal.privacy');
Route::get('/landing-wallpaper', [LandingController::class, 'wallpaper'])->name('landing.wallpaper');
Route::get('/platform-assets/{kind}', [PlatformBrandingController::class, 'asset'])->name('platform.asset');
Route::get('/pwa/{workspace}.webmanifest', [PlatformBrandingController::class, 'manifest'])->name('pwa.manifest');
Route::redirect('/superadmin/landing-appearance', '/superadmin/settings');

Route::get('/staff-photos/{type}/{id}', StaffPhotoController::class)
    ->middleware('auth:web,worker')->name('staff.photo');

Route::get('/manager/login', [ManagerLoginController::class, 'show'])->name('manager.login');
Route::post('/manager/login', [ManagerLoginController::class, 'login'])
    ->middleware('throttle:manager-pin')->name('manager.login.submit');

Route::post('/payments/paystack/webhook', PaystackWebhookController::class)->name('payments.paystack.webhook');
Route::post('/subscription-payments/paystack/webhook', [SubscriptionPaymentController::class, 'webhook'])->name('subscription.payment.webhook');
Route::middleware('auth')->group(function (): void {
    Route::post('/subscription-payments/{invoice}/checkout', [SubscriptionPaymentController::class, 'checkout'])->middleware('throttle:10,1')->name('subscription.payment.checkout');
    Route::get('/subscription-payments/callback', [SubscriptionPaymentController::class, 'callback'])->middleware('throttle:20,1')->name('subscription.payment.callback');
    Route::get('/subscription-notice', function () {
        abort_unless(auth()->user()->status === 'active' && in_array(auth()->user()->role, ['ceo', 'manager'], true), 403);

        return response()->view('shared.subscription-notice', ['role' => auth()->user()->role, 'tenant' => Tenant::withoutGlobalScopes()->findOrFail(auth()->user()->tenant_id)])->header('Cache-Control', 'no-store, private');
    })->name('subscription.notice');
});
Route::post('/manager/plate-scan', PlateOcrController::class)
    ->middleware(['auth', EnsureTenantSubscriptionActive::class])
    ->name('manager.plate-scan');

Route::prefix('client')->name('client.')->middleware(ResolveClientTenant::class)->group(function (): void {
    Route::get('/register', [ClientPortalController::class, 'registerForm'])->name('register');
    Route::get('/login', [ClientPortalController::class, 'loginForm'])->name('login');
    Route::post('/otp', [ClientPortalController::class, 'requestOtp'])
        ->middleware('throttle:client-otp')
        ->name('otp.request');
    Route::get('/verify', [ClientPortalController::class, 'verifyForm'])->name('verify');
    Route::post('/verify', [ClientPortalController::class, 'verifyOtp'])
        ->middleware('throttle:client-otp')
        ->name('otp.verify');
    Route::middleware(AuthenticateClientPortal::class)->group(function (): void {
        Route::get('/', [ClientPortalController::class, 'dashboard'])->name('dashboard');
        Route::post('/bookings', [ClientPortalController::class, 'book'])->name('bookings.create');
        Route::post('/logout', [ClientPortalController::class, 'logout'])->name('logout');
    });
});

Route::prefix('worker')->name('worker.')->group(function () {
    Route::get('/login', [WorkerPortalController::class, 'loginForm'])->name('login');
    Route::post('/login', [WorkerPortalController::class, 'login'])
        ->middleware('throttle:worker-pin')
        ->name('login.submit');
    Route::middleware(AuthenticateWorkerPortal::class)->group(function () {
        Route::get('/', [WorkerPortalController::class, 'dashboard'])->name('dashboard');
        Route::post('/payouts', [WorkerPortalController::class, 'requestPayout'])->name('payouts.request');
        Route::post('/logout', [WorkerPortalController::class, 'logout'])->name('logout');
        Route::get('/subscription-notice', function () {
            return response()->view('shared.subscription-notice', ['role' => 'worker', 'tenant' => Tenant::withoutGlobalScopes()->findOrFail(auth('worker')->user()->tenant_id)])->header('Cache-Control', 'no-store, private');
        })->name('subscription.notice');
    });
});

Route::get('/stop-impersonating', function () {
    $adminId = session()->pull('impersonator_id');
    abort_unless($adminId, 403);

    $admin = User::query()->findOrFail($adminId);
    abort_unless($admin->isSuperAdmin(), 403);

    Auth::login($admin);
    request()->session()->regenerate();

    return redirect('/superadmin');
})->middleware('auth')->name('impersonation.stop');
