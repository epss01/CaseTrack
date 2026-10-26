<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReassignCaseRequest;
use App\Http\Requests\StoreCaseRequest;
use App\Http\Requests\UpdateCaseRequest;
use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CaseController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * authorizeResource() wires CaseModelPolicy into every resource action:
     * index => viewAny, create/store => create, show => view,
     * edit/update => update, destroy => delete. A failed check aborts with 403
     * before the action runs, which is what stops an investigator from opening
     * another investigator's case by URL.
     *
     * It does not cover the custom actions (confirmDelete, reassignForm,
     * reassign) — those authorize explicitly.
     */
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(CaseModel::class, 'case');
    }

    /**
     * List the cases the current user is allowed to see, optionally narrowed
     * by a free-text search over docket no., title, and linked party names.
     */
    public function index(Request $request)
    {
        $search = $request->validate([
            // max:255 bounds the LIKE — a trust-boundary guard, not a UX one.
            'search' => ['nullable', 'string', 'max:255'],
        ])['search'] ?? null;

        $cases = CaseModel::visibleTo($request->user())
            ->search($search)
            ->with(['investigator', 'timeline'])
            ->latest('docket_no')
            ->paginate(15)
            ->withQueryString();

        return view('cases.index', ['cases' => $cases, 'search' => $search]);
    }

    /**
     * Show the case intake form.
     */
    public function create(Request $request)
    {
        return view('cases.create', [
            'investigators' => $this->assignableInvestigators($request->user()),
        ]);
    }

    /**
     * Docket a new case together with its parties and its opening timeline.
     */
    public function store(StoreCaseRequest $request)
    {
        $case = DB::transaction(function () use ($request) {
            $case = CaseModel::create([
                'docket_no' => $request->validated('docket_no'),
                'case_title' => $request->validated('case_title'),
                'incident_details' => $request->validated('incident_details'),
                'source_info' => $request->validated('source_info'),
                'investigator_id' => $request->validated('investigator_id'),
                'complexity_weight' => $request->validated('complexity_weight'),
                'is_torture_case' => $request->validated('is_torture_case'),
                'status' => CaseModel::STATUS_DOCKETED,
            ]);

            $case->complainants()->createMany($request->validated('complainants') ?? []);
            $case->victims()->createMany($request->validated('victims'));
            $case->respondents()->createMany($request->validated('respondents'));

            // Opening the timeline is what makes the Case Profile Matrix's
            // timeline section real; the milestone dates are set later.
            $case->timeline()->create([
                'date_of_docket' => $request->validated('date_of_docket'),
            ]);

            AuditLog::record($request->user(), $case, AuditLog::ACTION_CREATE);

            return $case;
        });

        return redirect()
            ->route('cases.show', $case)
            ->with('status', __('Case docketed.'));
    }

    /**
     * Show the Case Profile Matrix: the whole case on one read-only page.
     */
    public function show(CaseModel $case)
    {
        $case->load(['investigator', 'complainants', 'victims', 'respondents', 'timeline']);

        return view('cases.show', ['case' => $case]);
    }

    /**
     * Show the edit form for a case.
     */
    public function edit(Request $request, CaseModel $case)
    {
        return view('cases.edit', [
            'case' => $case,
            'onBehalf' => $request->user()->isSupervisor() && $case->investigator_id !== $request->user()->id,
        ]);
    }

    /**
     * Persist an edit.
     *
     * docket_no and investigator_id are deliberately not editable here:
     * reassigning a case is a supervisory action, not part of casework, and
     * lives in reassign() below. Closure and reopening use this same edit
     * workflow, with their additional authorization enforced below.
     *
     * Audited as ACTION_EDIT rather than ACTION_CASE_REASSIGNED.
     *
     * A supervisor editing a case they are not the assigned investigator on
     * must state why (CHR-Answers-2026-08-01, item 6): no hard technical
     * gate, since CHR described this as an accountability expectation for
     * leave/handover coverage, not a workflow to enforce. An investigator's
     * own cases stay exactly as unconditional as before — CaseModelPolicy is
     * untouched.
     */
    public function update(UpdateCaseRequest $request, CaseModel $case)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $case, $validated) {
            // Optimistic lock: lockForUpdate() so the compare-then-write can't
            // race a concurrent save between the read and the write below.
            $fresh = CaseModel::whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            $this->authorize('update', $fresh);

            if ($fresh->lock_version !== $validated['lock_version']) {
                throw ValidationException::withMessages([
                    'lock_version' => __('This case was updated by someone else while you were editing. Reload the page to see the latest version before saving again.'),
                ]);
            }

            $onBehalf = $request->user()->isSupervisor() && $fresh->investigator_id !== $request->user()->id;
            $closureAction = null;

            if ($fresh->status !== $validated['status']
                && ($fresh->status === CaseModel::STATUS_CLOSED || $validated['status'] === CaseModel::STATUS_CLOSED)) {
                $this->authorize('changeClosure', $fresh);
                $closureAction = $validated['status'] === CaseModel::STATUS_CLOSED
                    ? AuditLog::ACTION_CASE_CLOSED
                    : AuditLog::ACTION_CASE_REOPENED;
            }

            $fresh->fill(collect($validated)->except(['lock_version', 'reason'])->all());
            $fresh->lock_version++;
            $fresh->save();

            if ($onBehalf) {
                AuditLog::record($request->user(), $fresh, AuditLog::ACTION_EDIT_ON_BEHALF, notes: $validated['reason']);
            } else {
                AuditLog::record($request->user(), $fresh, AuditLog::ACTION_EDIT);
            }

            if ($closureAction !== null) {
                AuditLog::record($request->user(), $fresh, $closureAction);
            }
        });

        return redirect()
            ->route('cases.show', $case)
            ->with('status', __('Case updated.'));
    }

    /**
     * Ask a supervisor to confirm before a case is deleted.
     *
     * A custom action, so authorizeResource() does not cover it.
     */
    public function confirmDelete(CaseModel $case)
    {
        $this->authorize('delete', $case);

        return view('cases.confirm-delete', ['case' => $case]);
    }

    /**
     * Delete a case.
     *
     * The case is soft-deleted, so the audit entry written here keeps a
     * case_id that still resolves.
     */
    public function destroy(Request $request, CaseModel $case)
    {
        DB::transaction(function () use ($request, $case) {
            AuditLog::record($request->user(), $case, AuditLog::ACTION_DELETE);

            $case->delete();
        });

        return redirect()
            ->route('cases.index')
            ->with('status', __('Case :docket deleted.', ['docket' => $case->docket_no]));
    }

    /**
     * Show the reassignment form.
     */
    public function reassignForm(Request $request, CaseModel $case)
    {
        $this->authorize('reassign', $case);

        return view('cases.reassign', [
            'case' => $case,
            'investigators' => $this->assignableInvestigators($request->user()),
        ]);
    }

    /**
     * Move a case to another investigator, correcting the docket number if it
     * was mis-entered.
     */
    public function reassign(ReassignCaseRequest $request, CaseModel $case)
    {
        $this->authorize('reassign', $case);

        DB::transaction(function () use ($request, $case) {
            $fresh = CaseModel::whereKey($case->getKey())->lockForUpdate()->firstOrFail();

            $this->authorize('reassign', $fresh);

            $fresh->fill($request->validated());
            $fresh->lock_version++;
            $fresh->save();

            AuditLog::record($request->user(), $case, AuditLog::ACTION_CASE_REASSIGNED);
        });

        return redirect()
            ->route('cases.show', $case)
            ->with('status', __('Case reassigned.'));
    }

    /**
     * The investigators a supervisor may assign a new case to, least loaded
     * first.
     *
     * Ordered by Workload Capacity Score rather than by name, so the
     * investigator the score suggests sits at the top of the picker. Name
     * order is the tie-break, which is what an all-equal office falls back to.
     *
     * Investigators do not choose: the form has no picker for them and
     * StoreCaseRequest pins the assignment to themselves regardless.
     *
     * Offers only investigators who can actually take the case — approved
     * registration, still active. Role::assignableInvestigatorRule()
     * enforces the same set server-side, so a rejected/pending/deactivated
     * id can't be POSTed straight past this list either.
     *
     * @return Collection<int, User>
     */
    protected function assignableInvestigators(User $user)
    {
        if (! $user->isSupervisor()) {
            return new Collection;
        }

        return User::query()
            ->whereRelation('role', 'role_name', Role::INVESTIGATOR)
            ->approved()
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->sortBy(fn (User $investigator) => $investigator->workloadCapacityScore())
            ->values();
    }
}
