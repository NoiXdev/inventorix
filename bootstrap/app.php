<?php

use App\Http\Middleware\SecureHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the reverse proxy / load balancer so X-Forwarded-Proto is honored.
        // Required for signed URLs (e.g. email verification) to validate as https in prod.
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            // \App\Http\Middleware\SetLocale::class,
            SecureHeaders::class,
        ]);

        // The app has no standalone "login" route — authentication lives in the
        // Filament panel. Send unauthenticated web requests there.
        $middleware->redirectGuestsTo(fn () => route('filament.app.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
