<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // withRouting's `commands` argument only accepts a single routes-style
    // file (routes/console.php), which is a route path, not a class path —
    // app/Console/Commands is not auto-discovered without this. Needed the
    // moment the first custom Artisan command (make:admin) was added.
    ->withCommands([
        \App\Console\Commands\MakeAdminCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // Catches a session that outlives its account's standing: a login
        // blocked by LoginController::credentials() only stops a *new*
        // session from starting, so without this an already-authenticated
        // user deactivated mid-session (or rejected after somehow logging
        // in) would keep working until they logged out on their own.
        $middleware->web(append: [\App\Http\Middleware\EnsureAccountIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
