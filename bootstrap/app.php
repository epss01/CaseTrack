<?php

use App\Console\Commands\MakeAdminCommand;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\PreventDuplicateSubmissions;
use App\Http\Middleware\PreventResponseCaching;
use App\Models\AuditLog;
use App\Models\CaseModel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        MakeAdminCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware) {
        // Run outside route/session middleware so redirects and errors are covered too.
        $middleware->prepend(PreventResponseCaching::class);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'submission' => PreventDuplicateSubmissions::class,
        ]);

        // Catches a session that outlives its account's standing: a login
        // blocked by LoginController::credentials() only stops a *new*
        // session from starting, so without this an already-authenticated
        // user deactivated mid-session (or rejected after somehow logging
        // in) would keep working until they logged out on their own.
        $middleware->web(append: [EnsureAccountIsActive::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            for ($query = $e; $query !== null; $query = $query->getPrevious()) {
                if (! $query instanceof QueryException) {
                    continue;
                }

                // SQL, driver messages and exception objects can all contain case data.
                $sqlState = $query->errorInfo[0] ?? null;
                Log::error('Database query failed.', [
                    'exception_type' => $e::class,
                    'database_exception_type' => $query::class,
                    'sql_state' => is_string($sqlState) && preg_match('/^[A-Z0-9]{5}$/D', $sqlState) ? $sqlState : null,
                    'driver_code' => is_int($query->errorInfo[1] ?? null) ? $query->errorInfo[1] : null,
                    'file' => $query->getFile(),
                    'line' => $query->getLine(),
                    'trace' => array_map(fn (array $frame) => Arr::only($frame, ['file', 'line', 'class', 'function']), $query->getTrace()),
                ]);

                return false;
            }
        });

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
