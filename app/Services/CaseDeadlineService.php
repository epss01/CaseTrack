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
     * The 60th-day milestone is computed and conditionally surfaced.
     *
     * It binds only torture cases (is_torture_case === true). An extension-
     * request/grant field is also required (not yet implemented). This flag
     * gates the milestone's visibility, but it will only appear for cases
     * where is_torture_case === true.
     *
     * Turning this on before the extension-request/grant field exists would
     * apply the torture-case rule to cases with is_torture_case === true
     * (which is safe), but the milestone would remain invisible for
     * is_torture_case !== true (false or null) due to case-scoping in
     * statusesFor().
     *
     * The milestone becomes visible when BOTH:
     *  - This flag is true
     *  - The case's is_torture_case === true
     *
     * Once the extension-request/grant field is implemented, all three
     * conditions must be met for the milestone to be active.
     *
     * ponytail: SIXTY_DAY_ENABLED remains false pending clarification on the
     * "extension-request/grant field" requirement — see [this session's summary] —
     * flip once resolved.
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
     * mark until the ROP is filed, the 60th day until the RORP is filed, and the
     * 120-day mark until the FIR is.
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
            'discharged_by' => 'date_submission_60th_day_rorp',
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
     * This filter gates milestones by the SIXTY_DAY_ENABLED flag. The 60th day
     * additionally requires the case to be a torture case (is_torture_case ===
     * true), which is checked in statusesFor(). There is deliberately no second
     * entry point that returns the gated milestones anyway: that would be a door
     * left open for exactly the mistake the flag exists to prevent.
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
            // Skip 60th day for non-torture cases (is_torture_case !== true)
            if ($days === self::SIXTIETH_DAY && ($timeline->case->is_torture_case !== true)) {
                continue;
            }

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
     * The date a case counts as resolved: the day its FIR was filed.
     *
     * The FIR is the last document a case files (see statusesFor()), so it is the
     * one resolution date every imported and seeded case can carry. Null means
     * "not resolved, or resolved with no date recorded" — callers that need to
     * tell those apart check the case status.
     */
    public function resolvedOn(?CaseTimeline $timeline): ?CarbonImmutable
    {
        return $timeline?->date_fir_submitted === null
            ? null
            : CarbonImmutable::parse($timeline->date_fir_submitted)->startOfDay();
    }

    /**
     * Docketed inside the period, both ends inclusive. A case with no
     * timeline cannot be placed in any period, so it is never incoming.
     */
    public function isIncomingIn(?CaseTimeline $timeline, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        if ($timeline?->date_of_docket === null) {
            return false;
        }

        return CarbonImmutable::parse($timeline->date_of_docket)->startOfDay()
            ->betweenIncluded($from->startOfDay(), $to->startOfDay());
    }

    public function isResolvedIn(?CaseTimeline $timeline, CarbonImmutable $from, CarbonImmutable $to): bool
    {
        return $this->resolvedOn($timeline)?->betweenIncluded($from->startOfDay(), $to->startOfDay()) ?? false;
    }

    /**
     * Open on the given day: docketed on or before it, and not yet resolved
     * (or resolved only after it). This is what makes backlog carried in from
     * an earlier period count as pending.
     */
    public function isPendingAt(?CaseTimeline $timeline, CarbonImmutable $asOf): bool
    {
        if ($timeline?->date_of_docket === null) {
            return false;
        }

        $asOf = $asOf->startOfDay();

        if (CarbonImmutable::parse($timeline->date_of_docket)->startOfDay()->greaterThan($asOf)) {
            return false;
        }

        $resolved = $this->resolvedOn($timeline);

        return $resolved === null || $resolved->greaterThan($asOf);
    }

    /**
     * Calendar days in a period, both ends counted.
     */
    public static function daysInPeriod(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) $from->startOfDay()->diffInDays($to->startOfDay()) + 1;
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
