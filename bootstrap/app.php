<?php

use App\Models\AuditLog;
use App\Models\CaseModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        // Makes a denied request visible in the trail instead of leaving
        // reconnaissance-by-URL-probing invisible: a CaseModelPolicy denial
        // (AuthorizationException, before Laravel's default rendering turns
        // it into a 403) or an EnsureUserHasRole/abort_if() denial (already
        // an HttpException — also matches 404/405, hence the status check).
        // Returns null always: this is a side effect on the way to the
        // ordinary 403 response, never a replacement for it.
        $exceptions->render(function (AuthorizationException|HttpException $e, Request $request) {
            $user = $request->user();

            if ($user === null) {
                return null;
            }

            if ($e instanceof HttpException && $e->getStatusCode() !== 403) {
                return null;
            }

            $case = $request->route('case');

            DB::transaction(function () use ($user, $case) {
                AuditLog::record($user, $case instanceof CaseModel ? $case : null, AuditLog::ACTION_ACCESS_DENIED);
            });

            return null;
        });
    })->create();
