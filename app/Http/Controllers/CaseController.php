<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCaseRequest;
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
     * authorizeResource() wires CaseModelPolicy into every action:
     * index => viewAny, create/store => create, show => view,
     * edit/update => update. A failed check aborts with 403 before the action
     * runs, which is what stops an investigator from opening another
     * investigator's case by URL.
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
     * Docket a new case together with its victims and respondents.
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

            $case->victims()->createMany($request->validated('victims'));
            $case->respondents()->createMany($request->validated('respondents'));

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
        $case->load(['investigator', 'victims', 'respondents', 'timeline']);

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
     * reassigning a case is a supervisory action, not part of casework.
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
