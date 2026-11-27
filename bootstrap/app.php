<?php

use App\Http\Middleware\ApplyRuntimeSettings;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecureHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

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
            ApplyRuntimeSettings::class,
            SecureHeaders::class,
            HandleInertiaRequests::class,
        ]);

        // Filament is gone; every unauthenticated request now goes to the
        // Inertia app's own login page.
        $middleware->redirectGuestsTo(fn () => route('app.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Inertia requests that end in 403 get a proper in-app page instead of
        // the bare error response; other requests keep Laravel's default.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if ($response->getStatusCode() === 403 && $request->header('X-Inertia')) {
                return Inertia::render('errors/forbidden')->toResponse($request)
                    ->setStatusCode(403)
                    ->header('X-Inertia', 'true')
                    ->header('Vary', 'X-Inertia');
            }

            return $response;
        });
    })->create();
