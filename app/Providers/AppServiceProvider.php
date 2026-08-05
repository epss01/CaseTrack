<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The UI scaffolding is Bootstrap; the paginator defaults to Tailwind.
        Paginator::useBootstrapFive();

        // Fires on the event rather than a LoginController hook, so a
        // remember-me cookie resuming a session — which never touches the
        // controller — is still audited, not just a form login.
        //
        // Guarded on approved+active, the same two flags LoginController's
        // credentials() already gates on: SessionGuard::login() fires this
        // event unconditionally, including the moment RegistersUsers logs a
        // brand-new pending registrant in right before ::registered() logs
        // them straight back out, and the moment a remember-me cookie
        // resumes a since-deactivated account's session just before
        // EnsureAccountIsActive ends it. Neither session survives the
        // request, so recording ACTION_LOGIN for either would claim an
        // access that never actually happened.
        Event::listen(function (Login $event) {
            if ($event->user->registration_status === User::REGISTRATION_APPROVED && $event->user->is_active) {
                AuditLog::record($event->user, null, AuditLog::ACTION_LOGIN);
            }
        });
    }
}
