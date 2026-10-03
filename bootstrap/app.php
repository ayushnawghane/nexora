<?php

use App\Http\Middleware\EnsurePasswordIsFresh;
use App\Http\Middleware\EnsureTwoFactorIsVerified;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireRecentTwoFactor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'two-factor' => EnsureTwoFactorIsVerified::class,
            'two-factor.recent' => RequireRecentTwoFactor::class,
            'password.fresh' => EnsurePasswordIsFresh::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
