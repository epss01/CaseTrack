<?php

namespace App\Http\Middleware;

use App\Models\CaseModel;
use App\Models\User;
use App\Services\SubmissionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PreventDuplicateSubmissions
{
    public function __construct(private SubmissionService $submissions) {}

    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();
        $credentials = $user ? [$user->getAuthPassword(), (string) $user->getRememberToken()] : null;
        $sessionId = $user && config('session.driver') === 'database' && ! Auth::guard('web')->viaRemember()
            ? $request->session()->getId() : null;

        return $this->submissions->run($request,
            fn (bool $replaying, bool $locking, ?object $receipt = null) => $this->authorize($request, $ability, $replaying, $locking, $receipt, $credentials, $sessionId), $next);
    }

    private function authorize(Request $request, string $ability, bool $replaying, bool $locking, ?object $receipt, ?array $credentials, ?string $sessionId): void
    {
        if ($ability === 'register') {
            abort_if($request->user() !== null, 403);

            return;
        }
        $case = $request->route('case');
        if ($case) {
            $case = CaseModel::withTrashed()->whereKey($case->id)->when($locking, fn ($query) => $query->lockForUpdate())->firstOrFail();
            $request->route()->setParameter('case', $case);
        }
        $result = $receipt?->result_case_id !== null
            ? CaseModel::whereKey($receipt->result_case_id)->lockForUpdate()->firstOrFail() : null;
        $target = $request->route('user') ?? $request->route('investigator');
        if ($target) {
            $target = User::whereKey($target->id)->when($locking, fn ($query) => $query->lockForUpdate())->firstOrFail();
        }

        // A locking read after record waits sees current credentials under InnoDB repeatable read.
        $actor = User::whereKey($request->user()->id)->when($locking, fn ($query) => $query->sharedLock())->firstOrFail();
        $session = $sessionId === null ? null : DB::table('sessions')->where('id', $sessionId)->where('user_id', $actor->id)
            ->when($locking, fn ($query) => $query->sharedLock())->first();
        if (! hash_equals($credentials[0], $actor->getAuthPassword())
            || ! hash_equals($credentials[1], (string) $actor->getRememberToken())
            || ($sessionId !== null && ($session === null || $session->last_activity < time() - config('session.lifetime') * 60))) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Auth::guard('web')->forgetUser();
            abort(403);
        }
        abort_unless($actor->is_active && $actor->registration_status === User::REGISTRATION_APPROVED, 403);
        Auth::guard('web')->setUser($actor);

        if ($receipt?->requires_supervisor) {
            abort_unless($actor->isSupervisor(), 403);
        }
        if ($case) {
            Gate::forUser($actor)->authorize($ability, $case);
            abort_if($case->trashed() && ! $replaying, 404);

            return;
        }
        if ($ability === 'create') {
            Gate::forUser($actor)->authorize('create', CaseModel::class);
            if ($result) {
                Gate::forUser($actor)->authorize('view', $result);
            }

            return;
        }
        if ($ability === 'rating') {
            abort_unless($actor->isSupervisor() && $target->isInvestigator(), 403);

            return;
        }
        abort_unless($actor->isAdmin() && ! $actor->is($target), 403);
        abort_if($ability === 'role' && $target->isAdmin(), 403);
    }
}
