<?php

use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SetSecurityHeaders::class);

        /*
         * Lets the browser's own session authenticate an API route, so
         * `GET /api/v1/me` works from a page that is already signed in without
         * minting a token for it. Sanctum still accepts a bearer token from
         * clients that have no cookie — the player agent, for one.
         *
         * Only requests from SANCTUM_STATEFUL_DOMAINS are treated this way, and
         * they go through CSRF like any other session request.
         */
        $middleware->statefulApi();

        $middleware->web(append: [
            EnsureUserIsActive::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'device' => AuthenticateDevice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
