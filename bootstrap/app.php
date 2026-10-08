<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('carbay:sms-birthdays')->dailyAt('09:00');
        $schedule->command('carbay:sms-wash-reminders')->dailyAt('09:15');
        $schedule->command('carbay:billing-cycle')->dailyAt('00:20');
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('worker*') ? '/worker/login' : '/app/login');
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_PORT,
        );
        $middleware->validateCsrfTokens(except: ['payments/paystack/webhook', 'subscription-payments/paystack/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (HttpException $exception, Request $request) {
            if ($exception->getStatusCode() === 419 && $request->is('manager/login') && ! $request->expectsJson()) {
                return redirect('/manager/login', 303)->withErrors([
                    'session' => 'Your session expired. Please enter your phone number and PIN again.',
                ]);
            }
        });
    })->create();
