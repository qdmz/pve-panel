<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register middleware aliases
        $middleware->alias([
            'admin'          => \App\Http\Middleware\AdminMiddleware::class,
            'verified.email' => \App\Http\Middleware\VerifiedMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Custom exception handling is in app/Exceptions/Handler.php
    })
    ->booted(function () {
        // Load admin routes under /api/admin/ prefix with API middleware
        Route::middleware('api')->prefix('api')->group(function () {
            require base_path('routes/admin.php');
        });

        // SMTP config bridge: read from DB settings, fall back to .env
        try {
            $mailSettings = \App\Models\Setting::whereIn('group', ['email', 'smtp'])->pluck('value', 'key');
            if ($mailSettings->isNotEmpty()) {
                config([
                    'mail.mailers.smtp.host'       => $mailSettings->get('smtp_host') ?: env('MAIL_HOST'),
                    'mail.mailers.smtp.port'       => $mailSettings->get('smtp_port') ?: env('MAIL_PORT'),
                    'mail.mailers.smtp.username'   => $mailSettings->get('smtp_username') ?: env('MAIL_USERNAME'),
                    'mail.mailers.smtp.password'   => $mailSettings->get('smtp_password') ?: env('MAIL_PASSWORD'),
                    'mail.mailers.smtp.encryption' => $mailSettings->get('smtp_encryption') ?: env('MAIL_ENCRYPTION'),
                    'mail.from.address'            => $mailSettings->get('mail_from_address') ?: env('MAIL_FROM_ADDRESS'),
                    'mail.from.name'               => $mailSettings->get('mail_from_name') ?: env('MAIL_FROM_NAME'),
                ]);
            }
        } catch (\Exception $e) {
            // DB may not be available yet — graceful fallback
        }

        // Rate limiting for auth endpoints
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    })
    ->create();
