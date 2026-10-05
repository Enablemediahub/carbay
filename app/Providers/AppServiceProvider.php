<?php

namespace App\Providers;

use App\Auth\TenantUserProvider;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ServicePrice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WashSale;
use App\Models\Worker;
use App\Observers\AuditTrailObserver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('tenant-eloquent', function ($app, array $config): TenantUserProvider {
            return new TenantUserProvider($app['hash'], $config['model']);
        });

        foreach ([
            Branch::class,
            Expense::class,
            ServicePrice::class,
            Tenant::class,
            User::class,
            WashSale::class,
            Worker::class,
        ] as $model) {
            $model::observe(AuditTrailObserver::class);
        }

        RateLimiter::for('worker-pin', function (Request $request): array {
            $identity = strtolower((string) $request->input('company_email')).'|'
                .preg_replace('/\D+/', '', (string) $request->input('phone'));

            return [
                Limit::perMinute(5)->by('worker-identity:'.$identity),
                Limit::perMinute(30)->by('worker-ip:'.$request->ip()),
            ];
        });
    }
}
