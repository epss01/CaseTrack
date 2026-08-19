<?php

namespace App\Services;

use App\Models\CaseTimeline;
use Carbon\CarbonImmutable;

/**
 * The statutory deadline countdown.
 *
 * Every date calculation in the application lives here — nothing outside this
 * class may do date math on a case_timelines column (CLAUDE.md, Conventions;
 * enforced by the PostToolUse hook in .claude/hooks/guard-writes.php). It takes
 * a CaseTimeline and returns values, with no Eloquent queries of its own, so it
 * is unit-testable without a database.
 *
 * Deterministic and rule-based: fixed offsets from a stored date, no inference
 * of any kind. That is a delimitation of the project, not a style preference.
 */
class CaseDeadlineService
{
    /**
     * How close a deadline has to be before it counts as due soon.
     *
     * Inherited from DemoDataSeeder::bucketFor(), which used a bare 14, so the
     * seeded demo spread keeps meaning what its docblock says it means.
     */
    public const WARNING_WINDOW_DAYS = 14;

    /**
     * Days after the date of docket that each milestone falls on.
     *
     * These moved here from CaseTimelineFactory, which now delegates: a factory
     * is a development artifact and application code should not read its
     * constants. Calendar days — no business-day or holiday rule is specified
     * anywhere in the CHR source, and inventing one would not be deterministic
     * in the sense the project requires.
     */
    public const EXTENSION_DAYS = 30;

    public const SIXTIETH_DAY = 60;

    public const HUNDRED_TWENTIETH_DAY = 120;

    /**
     * The office's own target for the FIR, ahead of the 120-day deadline so a
     * case can be behind target while still inside the deadline. Not statutory,
     * so it is seeded and displayed but never raises an alert.
     */
    public const FIR_TARGET_DAYS = 100;

    /**
     * The 60th-day milestone is computed but never surfaced.
     *
     * It binds only torture cases. cases.is_torture_case now exists
     * (CHR-Answers-2026-08-01, item 3), which clears one of two blockers —
     * but it's nullable, since pre-existing cases have no ground truth for
     * it, and this flag still cannot be flipped on it alone. Turning it on
     * today would apply the torture-case rule to every case in the office
     * indiscriminately, including the ones marked "not determined," which is
     * worse than not showing it at all.
     *
     * Flip this to true once the milestone is scoped to is_torture_case AND
     * an extension-request/grant field exists AND a column records that a
     * 60th-day RORP was actually filed (currently only the FIR can discharge
     * it). None of those three is done by this change.
     */
    public const SIXTY_DAY_ENABLED = false;

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_DUE_SOON = 'due_soon';

    public const STATUS_ON_TRACK = 'on_track';

    /**
     * The statutory milestones, and the submission that discharges each.
     *
     * A deadline only binds while its submission is outstanding — the 30-day
     * mark until the ROP is filed, the 120-day mark until the FIR is. The 60th
     * day has no submission column of its own; nothing in the schema records
     * that a RORP was filed.
     *
     * target_date_fir is absent on purpose: it is the office's internal target,
     * not a statutory deadline, so missing it is not an alert.
     */
    private const MILESTONES = [
        self::EXTENSION_DAYS => [
            'column' => 'extension_30_days',
            'discharged_by' => 'date_submission_rop',
            'label' => '30-day (ROP)',
        ],
        self::SIXTIETH_DAY => [
            'column' => 'submission_60th_day',
            'discharged_by' => null,
            'label' => '60th day (RORP)',
        ],
        self::HUNDRED_TWENTIETH_DAY => [
            'column' => 'submission_120th_day',
            'discharged_by' => 'date_fir_submitted',
            'label' => '120th day (ROP)',
        ],
    ];

    /**
     * A deadline: the date of docket plus a fixed number of calendar days.
     *
     * Whole days throughout — a case docketed at 4pm is not half a day less
     * overdue than one docketed at 9am.
     */
    public static function deadlineOn(CarbonImmutable|string $docketedOn, int $days): CarbonImmutable
    {
        return CarbonImmutable::parse($docketedOn)->startOfDay()->addDays($days);
    }

    /**
     * Every date derived from a date of docket, ready to write to the columns.
     *
     * Used by CaseTimelineFactory and so by DemoDataSeeder. Deliberately
     * includes the 60th day: the column exists and gets populated, it is only
     * the *alerting* on it that SIXTY_DAY_ENABLED gates.
     *
     * @return array<string, CarbonImmutable>
     */
    public static function deadlinesFor(CarbonImmutable|string $docketedOn): array
    {
        $docketedOn = CarbonImmutable::parse($docketedOn)->startOfDay();

        return [
            'date_of_docket' => $docketedOn,
            'extension_30_days' => self::deadlineOn($docketedOn, self::EXTENSION_DAYS),
            'submission_60th_day' => self::deadlineOn($docketedOn, self::SIXTIETH_DAY),
            'submission_120th_day' => self::deadlineOn($docketedOn, self::HUNDRED_TWENTIETH_DAY),
            'target_date_fir' => self::deadlineOn($docketedOn, self::FIR_TARGET_DAYS),
        ];
    }

    /**
     * The milestones a user may be shown.
     *
     * This filter is the entire gate, and statusesFor() below is the only way
     * into a controller or a view — so a milestone missing from here cannot
     * reach rendered output, while deadlineOn() and deadlinesFor() go on
     * computing it. There is deliberately no second entry point that returns
     * the gated milestones anyway: that would be a door left open for exactly
     * the mistake the flag exists to prevent.
     *
     * @return array<int, array{column: string, discharged_by: string|null, label: string}>
     */
    public static function visibleMilestones(): array
    {
        return array_filter(
            self::MILESTONES,
            fn (int $days) => $days !== self::SIXTIETH_DAY || self::SIXTY_DAY_ENABLED,
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Where each visible milestone stands for one case, keyed by day offset.
     *
     * Returns an empty array when there is nothing to measure — a case docketed
     * before the timeline feature landed may have no case_timelines row at all
     * (CaseTimelineController uses updateOrCreate for exactly that reason).
     * That is not the same as "on track", and callers must not treat it as such.
     *
     * @return array<int, array{days: int, label: string, deadline: CarbonImmutable, status: string, days_remaining: int}>
     */
    public function statusesFor(?CaseTimeline $timeline, ?CarbonImmutable $today = null): array
    {
        if ($timeline?->date_of_docket === null) {
            return [];
        }

        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $docketedOn = CarbonImmutable::parse($timeline->date_of_docket)->startOfDay();

        // The FIR is the last document a case files, so once it is in nothing on
        // the timeline is outstanding regardless of which earlier columns were
        // left blank. DemoDataSeeder::bucketFor() has always asserted this with
        // its CLOSED bucket; it is stated once, here, now.
        $closedOut = $timeline->date_fir_submitted;

        $statuses = [];

        foreach (self::visibleMilestones() as $days => $milestone) {
            // The stored column is the office's own record of the deadline and
            // wins wherever it is set — the Set Timeline form is free entry with
            // no cross-field validation, so overriding what an investigator
            // typed would silently discard it. The offset is the fallback for
            // the timelines that only ever got a date of docket, which is what
            // lets those cases raise an alert at all.
            $deadline = $timeline->{$milestone['column']} !== null
                ? CarbonImmutable::parse($timeline->{$milestone['column']})->startOfDay()
                : self::deadlineOn($docketedOn, $days);

            $dischargedOn = $closedOut ?? ($milestone['discharged_by'] === null
                ? null
                : $timeline->{$milestone['discharged_by']});

            $statuses[$days] = [
                'days' => $days,
                'label' => $milestone['label'],
                'deadline' => $deadline,
                'status' => $this->statusOf($deadline, $dischargedOn !== null, $today),
                'days_remaining' => (int) $today->diffInDays($deadline, false),
            ];
        }

        return $statuses;
    }

    /**
     * The worst outstanding milestone for one case, or null if unmeasurable.
     *
     * Null means no timeline row and no date of docket — nothing to count from.
     * STATUS_SUBMITTED means every milestone is discharged; the case is done.
     */
    public function caseStatusFor(?CaseTimeline $timeline, ?CarbonImmutable $today = null): ?string
    {
        $statuses = $this->statusesFor($timeline, $today);

        if ($statuses === []) {
            return null;
        }

        $reported = array_column($statuses, 'status');

        foreach ([self::STATUS_OVERDUE, self::STATUS_DUE_SOON, self::STATUS_ON_TRACK] as $status) {
            if (in_array($status, $reported, true)) {
                return $status;
            }
        }

        return self::STATUS_SUBMITTED;
    }

    /**
     * One milestone's state, in the order the rules are applied.
     *
     * Submitted first: a discharged milestone is done, however long ago its
     * deadline passed. A deadline falling today is due soon, not overdue.
     */
    private function statusOf(CarbonImmutable $deadline, bool $discharged, CarbonImmutable $today): string
    {
        if ($discharged) {
            return self::STATUS_SUBMITTED;
        }

        if ($deadline->lessThan($today)) {
            return self::STATUS_OVERDUE;
        }

        if ($deadline->lessThanOrEqualTo($today->addDays(self::WARNING_WINDOW_DAYS))) {
            return self::STATUS_DUE_SOON;
        }

        return self::STATUS_ON_TRACK;
    }
}
