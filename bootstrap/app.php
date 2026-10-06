<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        $middleware->validateCsrfTokens(except: ['payments/paystack/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
