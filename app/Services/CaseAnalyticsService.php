<?php

namespace App\Services;

use App\Models\CaseModel;
use Carbon\CarbonImmutable;

/**
 * Descriptive office statistics over a set of cases.
 *
 * Counting, grouping and dividing only. Every comparison of a case_timelines
 * date is delegated to CaseDeadlineService (CLAUDE.md, Conventions), so this
 * class names no timeline column and stays unit-testable without a database.
 * Deterministic by design: no inference, no prediction.
 */
class CaseAnalyticsService
{
    public function __construct(private readonly CaseDeadlineService $deadlines) {}

    /**
     * CEPEJ Clearance Rate and Disposition Time for one period.
     *
     * $cases must be the visible set with its timeline loaded, and must NOT be
     * narrowed by the period itself: a case docketed last year and resolved this
     * period belongs in "resolved", and one still open belongs in "pending".
     * Filtering the set by docket date would drop both and corrupt the figures.
     *
     * Clearance Rate  = resolved / incoming x 100
     * Disposition Time = pending at period end / resolved x days in period
     *
     * The multiplier is the period's own length, not a fixed 365. The two agree
     * for a full non-leap year; for a quarter, 365 would overstate the backlog's
     * clearing time roughly fourfold.
     *
     * @param  iterable<CaseModel>  $cases
     * @return array{
     *     incoming: int, resolved: int, pending: int, period_days: int,
     *     clearance_rate: ?float, disposition_days: ?float,
     *     closed_without_date: int, untracked: int
     * }
     */
    public function clearanceAndDisposition(iterable $cases, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $incoming = $resolved = $pending = $closedWithoutDate = $untracked = 0;

        foreach ($cases as $case) {
            $timeline = $case->timeline;

            // Nothing to place in any period, and not the same as "zero".
            if ($timeline === null) {
                $untracked++;

                continue;
            }

            if ($this->deadlines->isIncomingIn($timeline, $from, $to)) {
                $incoming++;
            }

            if ($this->deadlines->isResolvedIn($timeline, $from, $to)) {
                $resolved++;
            }

            if ($this->deadlines->isPendingAt($timeline, $to)) {
                $pending++;
            }

            // Closed, but no FIR date: resolved in fact, unplaceable in time.
            // Reported rather than guessed into a period.
            if ($case->status === CaseModel::STATUS_CLOSED && $this->deadlines->resolvedOn($timeline) === null) {
                $closedWithoutDate++;
            }
        }

        $periodDays = CaseDeadlineService::daysInPeriod($from, $to);

        return [
            'incoming' => $incoming,
            'resolved' => $resolved,
            'pending' => $pending,
            'period_days' => $periodDays,
            // Undefined, not zero: no intake means no rate, and a resolved
            // count of zero means the backlog never clears at this pace.
            'clearance_rate' => $incoming > 0 ? round($resolved / $incoming * 100, 1) : null,
            'disposition_days' => $resolved > 0 ? round($pending / $resolved * $periodDays, 1) : null,
            'closed_without_date' => $closedWithoutDate,
            'untracked' => $untracked,
        ];
    }
}
