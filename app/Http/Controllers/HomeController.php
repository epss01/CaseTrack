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
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->isSupervisor()) {
            return $this->supervisorDashboard();
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

    /**
     * The office-wide caseload, and the closures waiting on a decision.
     *
     * Deliberately office-wide: no scopeVisibleTo(), no scoping to the
     * supervisor's own cases. A supervisor confirms or rejects any closure, not
     * only ones on cases they happen to hold, so a scoped query would hide the
     * work this page exists to surface. The office-wide read is safe because
     * this branch is only reachable by a supervisor and /home takes no route
     * parameter — there is nothing to substitute for another office.
     *
     * WCS and performance ratings stay on /workload, which is linked from the
     * page. Restating them here would compute the same active-case count from a
     * second query, and two figures that agree only by coincidence eventually
     * do not.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    private function supervisorDashboard()
    {
        // One grouped query covers all four figures. SoftDeletes scopes it for
        // free, so a deleted case is absent from every one of them.
        $byStatus = CaseModel::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // except(), not the forget() used above: forget() mutates in place, and
        // activeCount below is derived from the result rather than from a
        // separately loaded collection.
        //
        // Excluding one status from an already-grouped count is cheaper than a
        // second query, but the definition matches scopeActive() exactly —
        // everything but Closed — so a Pending Closure case counts as active
        // here for the same reason it does toward its investigator's WCS.
        $activeByStatus = $byStatus->except([CaseModel::STATUS_CLOSED]);

        // The queue this page is for. Unpaginated: it is bounded by how many
        // closures the office has open at once, not by the size of the archive,
        // and a decision queue that hides its tail is worse than a long one.
        //
        // ponytail: nothing enforces that bound — a docket-heavy month puts an
        // arbitrarily long table on the page a supervisor is meant to clear
        // quickly. Paginate at 15 to match CaseController::index() if it ever
        // stops fitting a screen.
        $pendingCases = CaseModel::query()
            ->where('status', CaseModel::STATUS_PENDING_CLOSURE)
            ->with('investigator')
            ->latest('docket_no')
            ->get();

        return view('supervisor-dashboard', [
            'pendingCases' => $pendingCases,
            'activeCount' => $activeByStatus->sum(),
            'pendingClosureCount' => $byStatus[CaseModel::STATUS_PENDING_CLOSURE] ?? 0,
            'closedCount' => $byStatus[CaseModel::STATUS_CLOSED] ?? 0,
            'totalCount' => $byStatus->sum(),
            'activeByStatus' => $activeByStatus,
        ]);
    }
}
