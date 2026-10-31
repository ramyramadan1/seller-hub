<?php

use App\Http\Middleware\EnsureOwner;
use App\Http\Middleware\SetTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->throttleApi();
        $middleware->alias([
            'tenant' => SetTenant::class,
            'owner' => EnsureOwner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // The SPA always expects JSON from /api/* (401 instead of a redirect to a login page).
        $exceptions->shouldRenderJsonWhen(fn ($request, $e) => $request->is('api/*') || $request->expectsJson());
    })->create();
