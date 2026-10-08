<?php

namespace App\Providers;

use App\Auth\TenantUserProvider;
use App\Models\Branch;
use App\Models\CashReconciliation;
use App\Models\Expense;
use App\Models\Job;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\ServicePrice;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WashSale;
use App\Models\Worker;
use App\Observers\AuditTrailObserver;
use App\Observers\CashReconciliationObserver;
use App\Observers\JobObserver;
use App\Observers\PayoutObserver;
use App\Support\PhoneNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
            Job::class,
            Payment::class,
            Payout::class,
        ] as $model) {
            $model::observe(AuditTrailObserver::class);
        }
        Job::observe(JobObserver::class);
        CashReconciliation::observe(CashReconciliationObserver::class);
        Payout::observe(PayoutObserver::class);

        RateLimiter::for('manager-pin', function (Request $request): array {
            $identity = (string) $request->input('company_id').'|'
                .preg_replace('/\D+/', '', PhoneNumber::normalize((string) $request->input('phone')) ?? '');

            return [
                Limit::perMinute(5)->by('manager-identity:'.$identity),
                Limit::perMinute(30)->by('manager-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('worker-pin', function (Request $request): array {
            $identity = (string) $request->input('company_id').'|'
                .preg_replace('/\D+/', '', PhoneNumber::normalize((string) $request->input('phone')) ?? '');

            return [
                Limit::perMinute(5)->by('worker-identity:'.$identity),
                Limit::perMinute(30)->by('worker-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('client-otp', function (Request $request): array {
            $phone = preg_replace('/\D+/', '', (string) $request->input('phone'));

            return [
                Limit::perMinute(3)->by('client-otp:'.$request->query('tenant').'|'.$phone),
                Limit::perMinute(20)->by('client-otp-ip:'.$request->ip()),
            ];
        });
    }
}
