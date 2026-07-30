<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReassignCaseRequest;
use App\Http\Requests\StoreCaseRequest;
use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
     * List the cases the current user is allowed to see.
     */
    public function index(Request $request)
    {
        $cases = CaseModel::visibleTo($request->user())
            ->with(['investigator', 'timeline'])
            ->latest('docket_no')
            ->paginate(15);

        return view('cases.index', ['cases' => $cases]);
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
    public function edit(CaseModel $case)
    {
        return view('cases.edit', ['case' => $case]);
    }

    /**
     * Persist an edit.
     *
     * docket_no and investigator_id are deliberately not editable here:
     * reassigning a case is a supervisory action, not part of casework, and
     * lives in reassign() below. The closure states are off limits for the
     * same reason — see CaseModel::statusRulesFor().
     *
     * Audited as ACTION_EDIT rather than ACTION_UPDATE, which reassign() has
     * already taken.
     */
    public function update(Request $request, CaseModel $case)
    {
        $validated = $request->validate([
            'case_title' => ['required', 'string', 'max:255'],
            'incident_details' => ['required', 'string'],
            'source_info' => ['nullable', 'string', 'max:255'],
            'status' => CaseModel::statusRulesFor($case),
            'complexity_weight' => CaseModel::complexityWeightRules(),
        ]);

        DB::transaction(function () use ($request, $case, $validated) {
            $case->update($validated);

            AuditLog::record($request->user(), $case, AuditLog::ACTION_EDIT);
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
     * Ask a supervisor to close this case.
     *
     * The Maker half of maker-checker. Proposing decides nothing on its own,
     * so it needs no confirmation page of its own — the case simply parks at
     * STATUS_PENDING_CLOSURE until a supervisor rules on it.
     */
    public function proposeClosure(Request $request, CaseModel $case)
    {
        $this->authorize('proposeClosure', $case);

        // Proposing twice would copy "Pending Closure" into
        // status_before_closure and destroy the value a rejection has to
        // restore. A double submit is a slip, not an attack, so it is turned
        // away with a message rather than a 403.
        if (in_array($case->status, [CaseModel::STATUS_PENDING_CLOSURE, CaseModel::STATUS_CLOSED], true)) {
            return redirect()
                ->route('cases.show', $case)
                ->with('status', __('This case is already :status.', ['status' => $case->status]));
        }

        DB::transaction(function () use ($request, $case) {
            $case->update([
                'status_before_closure' => $case->status,
                'status' => CaseModel::STATUS_PENDING_CLOSURE,
            ]);

            AuditLog::record($request->user(), $case, AuditLog::ACTION_CLOSURE_PROPOSED);
        });

        return redirect()
            ->route('cases.show', $case)
            ->with('status', __('Closure proposed. A supervisor has to confirm it before the case closes.'));
    }

    /**
     * Show a supervisor the proposed closure they are being asked to rule on.
     *
     * A real page rather than a scripted dialog, following confirm-delete:
     * the confirmation step has to survive with scripting off.
     */
    public function closureReview(CaseModel $case)
    {
        $this->authorize('resolveClosure', $case);

        // Authorized first, so someone who may not rule on closures gets a 403
        // either way and learns nothing about whether one is pending.
        abort_unless($case->status === CaseModel::STATUS_PENDING_CLOSURE, 404);

        return view('cases.confirm-closure', ['case' => $case]);
    }

    /**
     * Confirm or reject a proposed closure.
     *
     * The Checker half. Rejecting puts the case back exactly where it was,
     * which is what status_before_closure exists for — a case that was under
     * investigation should not look like it regressed to intake because
     * someone asked to close it.
     */
    public function resolveClosure(Request $request, CaseModel $case)
    {
        $this->authorize('resolveClosure', $case);

        abort_unless($case->status === CaseModel::STATUS_PENDING_CLOSURE, 404);

        $decision = $request->validate([
            'decision' => ['required', Rule::in(['confirm', 'reject'])],
        ])['decision'];

        DB::transaction(function () use ($request, $case, $decision) {
            $case->update([
                // The fallback only bites on a case parked at Pending Closure
                // by something other than propose() — seed data, or a hand
                // edit in the database.
                'status' => $decision === 'confirm'
                    ? CaseModel::STATUS_CLOSED
                    : ($case->status_before_closure ?? CaseModel::STATUS_DOCKETED),
                'status_before_closure' => null,
            ]);

            AuditLog::record($request->user(), $case, $decision === 'confirm'
                ? AuditLog::ACTION_CLOSURE_CONFIRMED
                : AuditLog::ACTION_CLOSURE_REJECTED);
        });

        return redirect()
            ->route('cases.show', $case)
            ->with('status', $decision === 'confirm'
                ? __('Case closed.')
                : __('Closure rejected. The case is back with its investigator.'));
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
            $case->update($request->validated());

            AuditLog::record($request->user(), $case, AuditLog::ACTION_UPDATE);
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
     * @return \Illuminate\Database\Eloquent\Collection<int, User>
     */
    protected function assignableInvestigators(User $user)
    {
        if (! $user->isSupervisor()) {
            return new Collection;
        }

        return User::query()
            ->whereRelation('role', 'role_name', Role::INVESTIGATOR)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->sortBy(fn (User $investigator) => $investigator->workloadCapacityScore())
            ->values();
    }
}
