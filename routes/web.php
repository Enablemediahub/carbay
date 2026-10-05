<?php

use App\Models\User;
use App\Http\Controllers\WorkerPortalController;
use App\Http\Middleware\AuthenticateWorkerPortal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/app'));

Route::prefix('worker')->name('worker.')->group(function () {
    Route::get('/login', [WorkerPortalController::class, 'loginForm'])->name('login');
    Route::post('/login', [WorkerPortalController::class, 'login'])
        ->middleware('throttle:worker-pin')
        ->name('login.submit');
    Route::middleware(AuthenticateWorkerPortal::class)->group(function () {
        Route::get('/', [WorkerPortalController::class, 'dashboard'])->name('dashboard');
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
