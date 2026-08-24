<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Services\CaseDeadlineService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AlertController extends Controller
{
    /**
     * The deadlines that need attention, worst first.
     *
     * A page of its own rather than badges on the two dashboards: those exist
     * to answer "what is my caseload" and "what needs a decision", and a third
     * job would have made both harder to read. Neither dashboard is touched.
     *
     * Role scoping is CaseModel::scopeVisibleTo(), the same scope /cases uses —
     * an investigator sees their own cases, a supervisor sees the office. There
     * is no per-case decision to make on a list page, so no policy class, the
     * same call /workload makes.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request, CaseDeadlineService $deadlines)
    {
        // ponytail: every active case in the office is still loaded and
        // classified in PHP per request — pagination below caps only what
        // renders, not the query. Classified in PHP rather than SQL because
        // the milestone deadline is COALESCE(stored column, date_of_docket +
        // N days): MySQL wants DATE_ADD(...), SQLite wants date(..., '+N
        // days'), tests only run SQLite and production only runs MySQL, so
        // that branch would ship untested. It would also restate the
        // discharge rules and the SIXTY_DAY_ENABLED gate outside
        // CaseDeadlineService, which CLAUDE.md forbids. Push the COALESCE
        // into driver-aware SQL if the loaded set itself stops fitting.
        $cases = CaseModel::query()
            ->visibleTo($request->user())
            ->active()
            ->with(['timeline', 'investigator'])
            ->get();

        $counts = [
            CaseDeadlineService::STATUS_OVERDUE => 0,
            CaseDeadlineService::STATUS_DUE_SOON => 0,
            CaseDeadlineService::STATUS_ON_TRACK => 0,
        ];

        $rows = [];
        $untracked = 0;

        foreach ($cases as $case) {
            $milestones = $deadlines->statusesFor($case->timeline);

            // No timeline row, or one with no date of docket: there is nothing
            // to count from. Reported separately rather than folded into "on
            // track", which would claim a case is fine that was never measured.
            if ($milestones === []) {
                $untracked++;

                continue;
            }

            // The milestone with the least time left is both the one to show and
            // the one that decides the case's state: overdue runs negative, due
            // soon spans nought to the warning window, on track is past it. So
            // one sort answers both questions and they cannot disagree.
            $driver = collect($milestones)
                ->reject(fn (array $milestone) => $milestone['status'] === CaseDeadlineService::STATUS_SUBMITTED)
                ->sortBy('days_remaining')
                ->first();

            // Every milestone discharged — the case has filed everything it owes
            // and belongs with the cases that need nothing done.
            $status = $driver['status'] ?? CaseDeadlineService::STATUS_ON_TRACK;

            $counts[$status]++;

            // The table is the work queue, so on-track cases stay in the tile
            // count and off the list.
            if ($status !== CaseDeadlineService::STATUS_ON_TRACK) {
                $rows[] = ['case' => $case, 'milestone' => $driver];
            }
        }

        usort($rows, fn (array $a, array $b) => $a['milestone']['days_remaining'] <=> $b['milestone']['days_remaining']);

        // In-memory pagination over the classified rows — see the ponytail:
        // comment above for why this isn't a query-level paginate().
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage();

        $rows = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('alerts.index', [
            'rows' => $rows,
            'overdueCount' => $counts[CaseDeadlineService::STATUS_OVERDUE],
            'dueSoonCount' => $counts[CaseDeadlineService::STATUS_DUE_SOON],
            'onTrackCount' => $counts[CaseDeadlineService::STATUS_ON_TRACK],
            'trackedCount' => array_sum($counts),
            'untrackedCount' => $untracked,
        ]);
    }
}
