<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePerformanceRatingRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;

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
            ->withCount(['cases as active_cases_count' => fn ($query) => $query->active()])
            ->withSum(['cases as active_complexity_sum' => fn ($query) => $query->active()], 'complexity_weight')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->sortBy(fn (User $investigator) => $investigator->workloadCapacityScore())
            ->values();

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
        $investigator->update($request->validated());

        AuditLog::record($request->user(), null, AuditLog::ACTION_UPDATE);

        return redirect()
            ->route('workload.index')
            ->with('status', __('Rating updated for :name.', ['name' => $investigator->full_name]));
    }
}
