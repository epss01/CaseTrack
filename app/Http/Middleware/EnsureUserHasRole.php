<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coarse-grained gate: the user must hold one of the listed roles to reach
 * the route at all. Per-record ownership is enforced separately by policies.
 *
 * Usage: ->middleware('role:Investigator,Supervisor')
 */
class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roleNames): Response
    {
        $user = $request->user();

        abort_if($user === null || ! $user->hasRole(...$roleNames), 403);

        return $next($request);
    }
}
