<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\User;
use App\Services\CaseDeadlineService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
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
     * @return Renderable
     */
    public function index(Request $request, CaseDeadlineService $deadlines)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $approved = User::query()->where('registration_status', User::REGISTRATION_APPROVED);

            return view('admin.dashboard', [
                'pendingCount' => User::where('registration_status', User::REGISTRATION_PENDING)->count(),
                'activeCount' => (clone $approved)->where('is_active', true)->count(),
                'inactiveCount' => (clone $approved)->where('is_active', false)->count(),
                'pending' => User::where('registration_status', User::REGISTRATION_PENDING)
                    ->with('role')->oldest()->orderBy('id')->limit(5)->get(),
            ]);
        }

        if ($user->isSupervisor()) {
            return $this->supervisorDashboard($deadlines);
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

        return view('dashboard', $this->attentionFor($cases, $deadlines) + [
            'cases' => $cases,
            'activeCount' => $cases->count(),
            'closedCount' => $byStatus[CaseModel::STATUS_CLOSED] ?? 0,
            'totalCount' => $byStatus->sum(),
            // The active mix only, so the bar and the table below it describe
            // the same set of cases.
            'activeByStatus' => $byStatus->forget(CaseModel::STATUS_CLOSED),
        ]);
    }

    /**
     * The office-wide caseload.
     *
     * Deliberately office-wide: no scopeVisibleTo(), no scoping to the
     * supervisor's own cases. The office-wide read is safe because
     * this branch is only reachable by a supervisor and /home takes no route
     * parameter — there is nothing to substitute for another office.
     *
     * WCS and performance ratings stay on /workload, which is linked from the
     * page. Restating them here would compute the same active-case count from a
     * second query, and two figures that agree only by coincidence eventually
     * do not.
     *
     * @return Renderable
     */
    private function supervisorDashboard(CaseDeadlineService $deadlines)
    {
        // One grouped query covers all three figures. SoftDeletes scopes it for
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
        // second query, but the definition matches scopeActive() exactly:
        // everything but Closed.
        $activeByStatus = $byStatus->except([CaseModel::STATUS_CLOSED]);

        // shortcut: classify active office timelines in PHP like Alerts; revisit if the loaded set stops fitting in memory.
        $cases = CaseModel::query()->active()->with(['timeline', 'investigator'])->get();

        return view('supervisor-dashboard', $this->attentionFor($cases, $deadlines) + [
            'activeCount' => $activeByStatus->sum(),
            'closedCount' => $byStatus[CaseModel::STATUS_CLOSED] ?? 0,
            'totalCount' => $byStatus->sum(),
            'activeByStatus' => $activeByStatus,
        ]);
    }

    private function attentionFor(Collection $cases, CaseDeadlineService $deadlines): array
    {
        $rows = [];
        $untracked = 0;

        foreach ($cases as $case) {
            if ($case->timeline?->date_of_docket === null) {
                $untracked++;

                continue;
            }

            $milestone = $deadlines->nextOutstandingFor($case->timeline);
            if ($milestone && in_array($milestone['status'], [CaseDeadlineService::STATUS_OVERDUE, CaseDeadlineService::STATUS_DUE_SOON], true)) {
                $rows[] = ['case' => $case, 'milestone' => $milestone];
            }
        }

        usort($rows, fn (array $a, array $b) => [$a['milestone']['days_remaining'], $a['case']->id] <=> [$b['milestone']['days_remaining'], $b['case']->id]);

        return ['attentionRows' => array_slice($rows, 0, 5), 'untrackedCount' => $untracked];
    }
}
