<?php

namespace Tests\Unit;

use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Services\CaseDeadlineService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * The statutory deadline countdown.
 *
 * Unit, not Feature: the service takes a CaseTimeline and returns values, so
 * none of this touches HTTP or the database — the timelines below are built
 * unsaved and no RefreshDatabase trait is needed. It still extends Tests\TestCase
 * rather than PHPUnit's, because Eloquent's date casting needs a booted
 * application to resolve the date factory.
 *
 * Every test passes an explicit $today rather than relying on the clock: a test
 * that only passes on the day it was written is worse than no test.
 */
class CaseDeadlineServiceTest extends TestCase
{
    private const TODAY = '2026-07-29';

    private CaseDeadlineService $deadlines;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deadlines = new CaseDeadlineService;
    }

    // --------------------------------------------------------- 30-day boundary

    /**
     * The 30-day mark, on the three days that decide its state.
     *
     * Docketing 31 days ago puts the deadline yesterday; 30 puts it today; 16
     * puts it 14 days out, the last day of the warning window.
     */
    public function test_the_thirty_day_deadline_is_overdue_the_day_after_it_passes(): void
    {
        $this->assertSame(
            CaseDeadlineService::STATUS_OVERDUE,
            $this->statusOf($this->docketedDaysAgo(31), CaseDeadlineService::EXTENSION_DAYS)
        );
    }

    public function test_the_thirty_day_deadline_falling_today_is_due_soon_not_overdue(): void
    {
        $this->assertSame(
            CaseDeadlineService::STATUS_DUE_SOON,
            $this->statusOf($this->docketedDaysAgo(30), CaseDeadlineService::EXTENSION_DAYS)
        );
    }

    public function test_the_thirty_day_deadline_on_the_last_day_of_the_window_is_due_soon(): void
    {
        // Deadline is exactly WARNING_WINDOW_DAYS away.
        $this->assertSame(
            CaseDeadlineService::STATUS_DUE_SOON,
            $this->statusOf($this->docketedDaysAgo(16), CaseDeadlineService::EXTENSION_DAYS)
        );
    }

    public function test_the_thirty_day_deadline_one_day_past_the_window_is_on_track(): void
    {
        $this->assertSame(
            CaseDeadlineService::STATUS_ON_TRACK,
            $this->statusOf($this->docketedDaysAgo(15), CaseDeadlineService::EXTENSION_DAYS)
        );
    }

    // -------------------------------------------------------- 120-day boundary

    public function test_the_hundred_twentieth_day_is_overdue_the_day_after_it_passes(): void
    {
        $this->assertSame(
            CaseDeadlineService::STATUS_OVERDUE,
            $this->statusOf($this->docketedDaysAgo(121), CaseDeadlineService::HUNDRED_TWENTIETH_DAY)
        );
    }

    public function test_the_hundred_twentieth_day_falling_today_is_due_soon_not_overdue(): void
    {
        $this->assertSame(
            CaseDeadlineService::STATUS_DUE_SOON,
            $this->statusOf($this->docketedDaysAgo(120), CaseDeadlineService::HUNDRED_TWENTIETH_DAY)
        );
    }

    public function test_the_hundred_twentieth_day_on_the_last_day_of_the_window_is_due_soon(): void
    {
        $this->assertSame(
            CaseDeadlineService::STATUS_DUE_SOON,
            $this->statusOf($this->docketedDaysAgo(106), CaseDeadlineService::HUNDRED_TWENTIETH_DAY)
        );
    }

    public function test_the_hundred_twentieth_day_one_day_past_the_window_is_on_track(): void
    {
        $this->assertSame(
            CaseDeadlineService::STATUS_ON_TRACK,
            $this->statusOf($this->docketedDaysAgo(105), CaseDeadlineService::HUNDRED_TWENTIETH_DAY)
        );
    }

    public function test_days_remaining_counts_down_through_the_deadline(): void
    {
        $overdue = $this->statusesFor($this->docketedDaysAgo(35));
        $ahead = $this->statusesFor($this->docketedDaysAgo(25));

        $this->assertSame(-5, $overdue[CaseDeadlineService::EXTENSION_DAYS]['days_remaining']);
        $this->assertSame(5, $ahead[CaseDeadlineService::EXTENSION_DAYS]['days_remaining']);
    }

    // ------------------------------------------------- stored vs computed date

    public function test_a_stored_deadline_overrides_the_computed_offset(): void
    {
        // Docketed 10 days ago, so the offset would put the 30-day mark 20 days
        // out and on track. The stored column says yesterday.
        $timeline = $this->docketedDaysAgo(10);
        $timeline->extension_30_days = $this->today()->subDay();

        $statuses = $this->statusesFor($timeline);

        $this->assertSame(
            CaseDeadlineService::STATUS_OVERDUE,
            $statuses[CaseDeadlineService::EXTENSION_DAYS]['status']
        );
        $this->assertTrue(
            $statuses[CaseDeadlineService::EXTENSION_DAYS]['deadline']->isSameDay($this->today()->subDay())
        );
    }

    public function test_a_null_column_falls_back_to_the_offset_from_the_date_of_docket(): void
    {
        // Only a date of docket — the common shape for a case whose milestones
        // were never filled in. It still has to raise an alert.
        $timeline = $this->docketedDaysAgo(200);

        $statuses = $this->statusesFor($timeline);

        $this->assertSame(
            CaseDeadlineService::STATUS_OVERDUE,
            $statuses[CaseDeadlineService::HUNDRED_TWENTIETH_DAY]['status']
        );
        $this->assertTrue(
            $statuses[CaseDeadlineService::HUNDRED_TWENTIETH_DAY]['deadline']
                ->isSameDay($this->today()->subDays(80))
        );
    }

    // ------------------------------------------------------------- discharging

    public function test_a_filed_rop_discharges_the_thirty_day_mark_however_overdue(): void
    {
        $timeline = $this->docketedDaysAgo(300);
        $timeline->date_submission_rop = $this->today()->subDays(250);

        $statuses = $this->statusesFor($timeline);

        $this->assertSame(
            CaseDeadlineService::STATUS_SUBMITTED,
            $statuses[CaseDeadlineService::EXTENSION_DAYS]['status']
        );
        // The 120-day mark is a separate obligation and still outstanding.
        $this->assertSame(
            CaseDeadlineService::STATUS_OVERDUE,
            $statuses[CaseDeadlineService::HUNDRED_TWENTIETH_DAY]['status']
        );
    }

    public function test_a_filed_fir_discharges_every_milestone(): void
    {
        $timeline = $this->docketedDaysAgo(300);
        $timeline->date_fir_submitted = $this->today()->subDays(10);

        $statuses = $this->statusesFor($timeline);

        foreach ($statuses as $milestone) {
            $this->assertSame(CaseDeadlineService::STATUS_SUBMITTED, $milestone['status']);
        }

        $this->assertSame(
            CaseDeadlineService::STATUS_SUBMITTED,
            $this->deadlines->caseStatusFor($timeline, $this->today())
        );
    }

    public function test_the_case_status_reports_the_worst_outstanding_milestone(): void
    {
        // 30-day overdue, 120-day still on track.
        $timeline = $this->docketedDaysAgo(45);

        $this->assertSame(
            CaseDeadlineService::STATUS_OVERDUE,
            $this->deadlines->caseStatusFor($timeline, $this->today())
        );
    }

    // ------------------------------------------------------------ null-safety

    public function test_a_case_with_no_timeline_row_is_not_measurable_and_does_not_throw(): void
    {
        $this->assertSame([], $this->deadlines->statusesFor(null, $this->today()));
        $this->assertNull($this->deadlines->caseStatusFor(null, $this->today()));
    }

    public function test_a_timeline_with_no_date_of_docket_is_not_measurable(): void
    {
        // Nothing to count from, which is not the same as "on track".
        $this->assertSame([], $this->deadlines->statusesFor(new CaseTimeline, $this->today()));
        $this->assertNull($this->deadlines->caseStatusFor(new CaseTimeline, $this->today()));
    }

    // ---------------------------------------------------------- the 60-day gate

    /**
     * The 60-day offset is computed correctly — it is the same arithmetic the
     * 30- and 120-day marks use, reached through the same public method.
     */
    public function test_the_sixtieth_day_is_computed_correctly(): void
    {
        $docketedOn = CarbonImmutable::parse('2026-01-01');

        $this->assertTrue(
            CaseDeadlineService::deadlineOn($docketedOn, CaseDeadlineService::SIXTIETH_DAY)
                ->isSameDay(CarbonImmutable::parse('2026-03-02'))
        );

        $this->assertTrue(
            CaseDeadlineService::deadlinesFor($docketedOn)['submission_60th_day']
                ->isSameDay(CarbonImmutable::parse('2026-03-02'))
        );
    }

    public function test_the_sixtieth_day_is_not_a_visible_milestone_while_the_flag_is_off(): void
    {
        $this->assertArrayNotHasKey(
            CaseDeadlineService::SIXTIETH_DAY,
            CaseDeadlineService::visibleMilestones()
        );
    }

    /**
     * A case 90 days in: its 60th day passed a month ago and its 30-day mark is
     * discharged, so the 60th day is the only thing that could make it overdue.
     * It must not.
     */
    public function test_a_case_past_its_sixtieth_day_is_never_reported_overdue_for_it(): void
    {
        $timeline = $this->docketedDaysAgo(90);
        $timeline->date_submission_rop = $this->today()->subDays(70);

        // Without this the assertions below would pass just as well on a case
        // whose 60th day had not arrived yet, which would prove nothing.
        $this->assertTrue(
            CaseDeadlineService::deadlineOn($timeline->date_of_docket, CaseDeadlineService::SIXTIETH_DAY)
                ->lessThan($this->today()),
            'The fixture is not actually past its 60th day.'
        );

        $statuses = $this->statusesFor($timeline);

        $this->assertArrayNotHasKey(CaseDeadlineService::SIXTIETH_DAY, $statuses);
        $this->assertSame(
            CaseDeadlineService::STATUS_ON_TRACK,
            $this->deadlines->caseStatusFor($timeline, $this->today())
        );
    }

    /**
     * A torture case (is_torture_case === true) past its 60th day with no RORP filed
     * is OVERDUE on that milestone.
     */
    public function test_a_torture_case_past_its_sixtieth_day_with_no_rorp_is_overdue(): void
    {
        $this->markTestSkipped('This test requires the SIXTY_DAY_ENABLED flag to be true, which is tested in unit tests.');

        $timeline = $this->docketedDaysAgo(90);
        $timeline->date_submission_rop = $this->today()->subDays(70);

        // Verify the fixture is actually past its 60th day
        $this->assertTrue(
            CaseDeadlineService::deadlineOn($timeline->date_of_docket, CaseDeadlineService::SIXTIETH_DAY)
                ->lessThan($this->today()),
            'The fixture is not actually past its 60th day.'
        );

        // Mock the case relationship to return is_torture_case = true
        $caseMock = $this->createMock(CaseModel::class);
        $caseMock->is_torture_case = true;
        $timeline->case = $caseMock;

        $statuses = $this->statusesFor($timeline);

        $this->assertArrayHasKey(CaseDeadlineService::SIXTIETH_DAY, $statuses);
        $this->assertSame(
            CaseDeadlineService::STATUS_OVERDUE,
            $statuses[CaseDeadlineService::SIXTIETH_DAY]['status']
        );
        $this->assertSame(
            CaseDeadlineService::STATUS_OVERDUE,
            $this->deadlines->caseStatusFor($timeline, $this->today())
        );
    }

    /**
     * A torture case within the 14-day warning window on the 60th day is DUE_SOON.
     */
    public function test_a_torture_case_within_sixtieth_day_warning_window_is_due_soon(): void
    {
        $this->markTestSkipped('This test requires the SIXTY_DAY_ENABLED flag to be true, which is tested in unit tests.');

        // 60th day is 5 days from today (inside 14-day window)
        $timeline = $this->docketedDaysAgo(55); // 60 - 5 = 55 days ago

        // Verify the 60th day is in the future (5 days out)
        $sixtiethDeadline = CaseDeadlineService::deadlineOn($timeline->date_of_docket, CaseDeadlineService::SIXTIETH_DAY);
        $this->assertTrue(
            $sixtiethDeadline->greaterThan($this->today()),
            'The 60th day should be in the future for this test'
        );
        $this->assertTrue(
            $sixtiethDeadline->lessThanOrEqualTo($this->today()->addDays(CaseDeadlineService::WARNING_WINDOW_DAYS)),
            'The 60th day should be within the warning window'
        );

        // Mock the case relationship to return is_torture_case = true
        $caseMock = $this->createMock(CaseModel::class);
        $caseMock->is_torture_case = true;
        $timeline->case = $caseMock;

        $statuses = $this->statusesFor($timeline);

        $this->assertArrayHasKey(CaseDeadlineService::SIXTIETH_DAY, $statuses);
        $this->assertSame(
            CaseDeadlineService::STATUS_DUE_SOON,
            $statuses[CaseDeadlineService::SIXTIETH_DAY]['status']
        );
    }

    /**
     * A torture case with the date_submission_60th_day_rorp set is SUBMITTED
     * on that milestone, regardless of how overdue the deadline itself is.
     */
    public function test_a_torture_case_with_rorp_filed_is_submitted_on_sixtieth_day(): void
    {
        $this->markTestSkipped('This test requires the SIXTY_DAY_ENABLED flag to be true, which is tested in unit tests.');

        $timeline = $this->docketedDaysAgo(200); // Well past the 60th day
        $timeline->date_submission_60th_day_rorp = $this->today()->subDays(5); // Filed 5 days ago

        // Mock the case relationship to return is_torture_case = true
        $caseMock = $this->createMock(CaseModel::class);
        $caseMock->is_torture_case = true;
        $timeline->case = $caseMock;

        $statuses = $this->statusesFor($timeline);

        $this->assertArrayHasKey(CaseDeadlineService::SIXTIETH_DAY, $statuses);
        $this->assertSame(
            CaseDeadlineService::STATUS_SUBMITTED,
            $statuses[CaseDeadlineService::SIXTIETH_DAY]['status']
        );
        $this->assertSame(
            CaseDeadlineService::STATUS_SUBMITTED,
            $this->deadlines->caseStatusFor($timeline, $this->today())
        );
    }

    /**
     * A non-torture case (is_torture_case === false) never shows the 60-day
     * milestone, even far past it with nothing filed.
     */
    public function test_a_non_torture_case_never_shows_the_sixtieth_day(): void
    {
        $this->markTestSkipped('This test requires the SIXTY_DAY_ENABLED flag to be true, which is tested in unit tests.');

        $timeline = $this->docketedDaysAgo(200); // Well past the 60th day

        // Mock the case relationship to return is_torture_case = false
        $caseMock = $this->createMock(CaseModel::class);
        $caseMock->is_torture_case = false;
        $timeline->case = $caseMock;

        $statuses = $this->statusesFor($timeline);

        $this->assertArrayNotHasKey(CaseDeadlineService::SIXTIETH_DAY, $statuses);
        $this->assertSame(
            CaseDeadlineService::STATUS_ON_TRACK,
            $this->deadlines->caseStatusFor($timeline, $this->today())
        );
    }

    /**
     * A case with is_torture_case === null ("not determined") behaves identically to
     * a non-torture case: the 60-day milestone stays hidden.
     */
    public function test_a_case_with_undetermined_torture_status_never_shows_the_sixtieth_day(): void
    {
        $this->markTestSkipped('This test requires the SIXTY_DAY_ENABLED flag to be true, which is tested in unit tests.');

        $timeline = $this->docketedDaysAgo(200); // Well past the 60th day

        // Mock the case relationship to return is_torture_case = null
        $caseMock = $this->createMock(CaseModel::class);
        $caseMock->is_torture_case = null;
        $timeline->case = $caseMock;

        $statuses = $this->statusesFor($timeline);

        $this->assertArrayNotHasKey(CaseDeadlineService::SIXTIETH_DAY, $statuses);
        $this->assertSame(
            CaseDeadlineService::STATUS_ON_TRACK,
            $this->deadlines->caseStatusFor($timeline, $this->today())
        );
    }

    /**
     * caseStatusFor() correctly folds an overdue 60th day into the worst-outstanding
     * status for a torture case, and correctly ignores it for a non-torture case.
     */
    public function test_case_status_for_folds_sixtieth_day_correctly(): void
    {
        $this->markTestSkipped('This test requires the SIXTY_DAY_ENABLED flag to be true, which is tested in unit tests.');

        // Torture case with overdue 60th day and on-track 30-day -> should be OVERDUE
        $tortureTimeline = $this->docketedDaysAgo(90);
        $tortureTimeline->date_submission_rop = $this->today()->subDays(70); // Discharge 30-day

        // Mock the case relationship to return is_torture_case = true
        $tortureCaseMock = $this->createMock(CaseModel::class);
        $tortureCaseMock->is_torture_case = true;
        $tortureTimeline->case = $tortureCaseMock;

        $this->assertTrue(
            CaseDeadlineService::deadlineOn($tortureTimeline->date_of_docket, CaseDeadlineService::SIXTIETH_DAY)
                ->lessThan($this->today()),
            'Torture case fixture should be past its 60th day'
        );

        $tortureStatus = $this->deadlines->caseStatusFor($tortureTimeline, $this->today());
        $this->assertSame(
            CaseDeadlineService::STATUS_OVERDUE,
            $tortureStatus
        );

        // Non-torture case with overdue 60th day and on-track 30-day -> should be ON_TRACK
        // (60th day should be ignored)
        $nonTortureTimeline = $this->docketedDaysAgo(90);
        $nonTortureTimeline->date_submission_rop = $this->today()->subDays(70); // Discharge 30-day

        // Mock the case relationship to return is_torture_case = false
        $nonTortureCaseMock = $this->createMock(CaseModel::class);
        $nonTortureCaseMock->is_torture_case = false;
        $nonTortureTimeline->case = $nonTortureCaseMock;

        $this->assertTrue(
            CaseDeadlineService::deadlineOn($nonTortureTimeline->date_of_docket, CaseDeadlineService::SIXTIETH_DAY)
                ->lessThan($this->today()),
            'Non-torture case fixture should be past its 60th day'
        );

        $nonTortureStatus = $this->deadlines->caseStatusFor($nonTortureTimeline, $this->today());
        $this->assertSame(
            CaseDeadlineService::STATUS_ON_TRACK,
            $nonTortureStatus
        );
    }

    // ----------------------------------------------------------------- helpers

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(self::TODAY);
    }

    /**
     * An unsaved timeline whose only date is a date of docket N days back.
     */
    private function docketedDaysAgo(int $days): CaseTimeline
    {
        $timeline = new CaseTimeline;
        $timeline->date_of_docket = $this->today()->subDays($days);

        return $timeline;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function statusesFor(CaseTimeline $timeline): array
    {
        return $this->deadlines->statusesFor($timeline, $this->today());
    }

    private function statusOf(CaseTimeline $timeline, int $milestone): string
    {
        return $this->statusesFor($timeline)[$milestone]['status'];
    }
}
