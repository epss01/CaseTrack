<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends an already-authenticated session the moment its account stops being
 * approved and active. LoginController::credentials() only gates a new
 * login attempt; without this, a session started before an account was
 * deactivated (or rejected) would silently keep working.
 *
 * Runs on every web request rather than a route group, since the whole
 * point is that there is no route an already-logged-in, now-disqualified
 * user should be able to reach.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && (! $user->is_active || $user->registration_status !== User::REGISTRATION_APPROVED)) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
