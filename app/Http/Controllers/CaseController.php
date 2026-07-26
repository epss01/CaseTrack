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
            ->with('investigator')
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
     * lives in reassign() below.
     */
    public function update(Request $request, CaseModel $case)
    {
        $validated = $request->validate([
            'case_title' => ['required', 'string', 'max:255'],
            'incident_details' => ['required', 'string'],
            'source_info' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'complexity_weight' => ['required', 'integer', 'min:0'],
        ]);

        $case->update($validated);

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
            $case->update($request->validated());

            AuditLog::record($request->user(), $case, AuditLog::ACTION_UPDATE);
        });

        return redirect()
            ->route('cases.show', $case)
            ->with('status', __('Case reassigned.'));
    }

    /**
     * The investigators a supervisor may assign a new case to.
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
            ->get();
    }
}
