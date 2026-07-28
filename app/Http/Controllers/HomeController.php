<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the dashboard a user lands on after signing in.
     *
     * This is the post-login destination for everyone (Auth\LoginController
     * sends every role to /home), so the role decision has to happen here
     * rather than in route middleware. The investigator caseload is read
     * through $user->cases(), which is scoped to investigator_id at source —
     * there is no route parameter to swap for someone else's caseload.
     *
     * @return \Illuminate\Contracts\Support\Renderable|\Illuminate\Http\RedirectResponse
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // The supervisor dashboard is a separate feature. Until it exists, send
        // supervisors to the office-wide case list rather than to an
        // investigator view that would always be empty for them.
        if ($user->isSupervisor()) {
            return redirect()->route('cases.index');
        }

        // Anyone holding neither case-handling role keeps the plain landing
        // page: there is no caseload to show them.
        if (! $user->isInvestigator()) {
            return view('home');
        }

        // ponytail: unpaginated, so the counts come free off the loaded
        // collection instead of costing a query each. One investigator's active
        // caseload fits a page — keeping it that way is what the Workload
        // Capacity Score is for — and /cases paginates at 15 if one outgrows it.
        //
        // Ordered by docket number, matching CaseController::index(). Not by
        // submission_120th_day: ordering by that date would assert 120 days is
        // *the* deadline, and for torture cases it is the 60th day — the
        // decision still blocked on a missing case-type field. That ordering
        // belongs with the countdown feature, so the two agree.
        $cases = $user->cases()
            ->active()
            ->with('timeline')
            ->latest('docket_no')
            ->get();

        // One grouped query covers every figure on the page. Counts by status
        // are the dashboard aggregate the project brief calls for, and
        // SoftDeletes scopes this for free.
        $byStatus = $user->cases()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('dashboard', [
            'cases' => $cases,
            'activeCount' => $cases->count(),
            'pendingClosureCount' => $byStatus[CaseModel::STATUS_PENDING_CLOSURE] ?? 0,
            'closedCount' => $byStatus[CaseModel::STATUS_CLOSED] ?? 0,
            'totalCount' => $byStatus->sum(),
            // The active mix only, so the bar and the table below it describe
            // the same set of cases.
            'activeByStatus' => $byStatus->forget(CaseModel::STATUS_CLOSED),
        ]);
    }
}
