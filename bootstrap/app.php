<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');

        $middleware->validateCsrfTokens(except: [
            'midtrans/notification',
            'midtrans/barter/notification',
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
        ]);

        $middleware->web(append: [
            // Dipasang sebelum HandleInertiaRequests supaya pengguna yang
            // akunnya baru dinonaktifkan tidak sempat dibagikan datanya ke
            // props Inertia.
            EnsureAccountIsActive::class,
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
