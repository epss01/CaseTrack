<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePerformanceRatingRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The office's workload picture, and the one place P_i is set.
 *
 * Supervisor-only, enforced by the role middleware on the route group rather
 * than by a policy: nothing here is scoped to an individual case, which is all
 * CaseModelPolicy knows how to reason about.
 */
class WorkloadController extends Controller
{
    public function index()
    {
        $investigators = User::query()
            ->whereRelation('role', 'role_name', Role::INVESTIGATOR)
            // Rejected/pending registrants were never real users and hold no
            // cases — excluded. is_active is deliberately NOT filtered: a
            // deactivated investigator is departed but may still hold an
            // active caseload, which needs to stay visible here to be
            // reassigned rather than vanish unseen.
            ->approved()
            ->withCount(['cases as active_cases_count' => fn ($query) => $query->active()])
            ->withSum(['cases as active_complexity_sum' => fn ($query) => $query->active()], 'complexity_weight')
            // WCS_i = active_complexity_sum * (2 - performance_rating) — the
            // same formula User::workloadCapacityScore() computes, done in SQL
            // so the ranking can be paginated. COALESCE matters: withSum()
            // yields NULL, not 0, for an investigator with no active cases,
            // and NULL * anything sorts as NULL.
            ->orderByRaw('COALESCE(active_complexity_sum, 0) * (2 - performance_rating)')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('workload.index', ['investigators' => $investigators]);
    }

    /**
     * Set an investigator's performance rating.
     *
     * Audited: the rating scales every score this page ranks by, so a silent
     * change to it would silently change who gets assigned casework.
     */
    public function update(UpdatePerformanceRatingRequest $request, User $investigator)
    {
        DB::transaction(function () use ($request, $investigator) {
            $investigator->update($request->validated());

            AuditLog::record($request->user(), null, AuditLog::ACTION_UPDATE, $investigator);
        });

        return redirect()
            ->route('workload.index')
            ->with('status', __('Rating updated for :name.', ['name' => $investigator->full_name]));
    }
}
