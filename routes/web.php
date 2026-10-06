<?php

use App\Http\Controllers\ClientPortalController;
use App\Http\Controllers\PaystackWebhookController;
use App\Http\Controllers\PlateOcrController;
use App\Http\Controllers\WorkerPortalController;
use App\Http\Middleware\AuthenticateClientPortal;
use App\Http\Middleware\AuthenticateWorkerPortal;
use App\Http\Middleware\ResolveClientTenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/app'));

Route::post('/payments/paystack/webhook', PaystackWebhookController::class)->name('payments.paystack.webhook');
Route::post('/manager/plate-scan', PlateOcrController::class)
    ->middleware('auth')
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
