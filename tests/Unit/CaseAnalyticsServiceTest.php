<?php

namespace Tests\Unit;

use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Services\CaseAnalyticsService;
use App\Services\CaseDeadlineService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Clearance Rate and Disposition Time.
 *
 * Unit, not Feature: cases are built unsaved with their timeline relation set
 * by hand, so no database is touched. Extends Tests\TestCase for the same
 * reason CaseDeadlineServiceTest does (Eloquent date casting needs a booted app).
 * The period is fixed, never the clock.
 */
class CaseAnalyticsServiceTest extends TestCase
{
    private CaseAnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analytics = new CaseAnalyticsService(new CaseDeadlineService);
    }

    private function periodFrom(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-01-01');
    }

    private function periodTo(): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-06-30'); // 181 days
    }

    public function test_clearance_rate_and_disposition_time_over_a_period(): void
    {
        $result = $this->analytics->clearanceAndDisposition([
            $this->caseWith('2026-02-01', '2026-03-01'), // incoming and resolved
            $this->caseWith('2025-11-01', '2026-02-10'), // carried in, resolved this period
            $this->caseWith('2026-03-01'),               // incoming, still open
            $this->caseWith('2025-10-01'),               // carried in, still open
            $this->caseWith('2026-07-15'),               // after the period: counts nowhere
        ], $this->periodFrom(), $this->periodTo());

        $this->assertSame(2, $result['incoming']);
        $this->assertSame(2, $result['resolved']);
        $this->assertSame(2, $result['pending']);
        $this->assertSame(181, $result['period_days']);
        $this->assertSame(100.0, $result['clearance_rate']);
        $this->assertSame(181.0, $result['disposition_days']);
    }

    public function test_a_case_resolved_after_the_period_is_still_pending_at_its_end(): void
    {
        $result = $this->analytics->clearanceAndDisposition([
            $this->caseWith('2026-01-10', '2026-08-01'),
        ], $this->periodFrom(), $this->periodTo());

        $this->assertSame(1, $result['incoming']);
        $this->assertSame(0, $result['resolved']);
        $this->assertSame(1, $result['pending']);
    }

    public function test_the_period_ends_are_inclusive(): void
    {
        $result = $this->analytics->clearanceAndDisposition([
            $this->caseWith('2026-01-01', '2026-06-30'),
        ], $this->periodFrom(), $this->periodTo());

        $this->assertSame(1, $result['incoming']);
        $this->assertSame(1, $result['resolved']);
        $this->assertSame(0, $result['pending']);
    }

    public function test_no_intake_leaves_the_clearance_rate_undefined_not_zero(): void
    {
        $result = $this->analytics->clearanceAndDisposition([
            $this->caseWith('2025-10-01', '2026-02-10'),
        ], $this->periodFrom(), $this->periodTo());

        $this->assertSame(0, $result['incoming']);
        $this->assertNull($result['clearance_rate']);
    }

    public function test_nothing_resolved_leaves_the_disposition_time_undefined_not_zero(): void
    {
        $result = $this->analytics->clearanceAndDisposition([
            $this->caseWith('2026-02-01'),
        ], $this->periodFrom(), $this->periodTo());

        $this->assertSame(0, $result['resolved']);
        $this->assertNull($result['disposition_days']);
    }

    public function test_disposition_time_scales_with_the_period_length(): void
    {
        // 1 pending, 1 resolved over a 31-day month: 31 days, not 365.
        $result = $this->analytics->clearanceAndDisposition([
            $this->caseWith('2026-01-05', '2026-01-20'),
            $this->caseWith('2026-01-10'),
        ], CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-31'));

        $this->assertSame(31.0, $result['disposition_days']);
    }

    public function test_a_closed_case_with_no_fir_date_is_reported_not_placed(): void
    {
        $result = $this->analytics->clearanceAndDisposition([
            $this->caseWith('2026-02-01', null, CaseModel::STATUS_CLOSED),
        ], $this->periodFrom(), $this->periodTo());

        $this->assertSame(1, $result['closed_without_date']);
        $this->assertSame(0, $result['resolved']);
    }

    public function test_a_case_with_no_timeline_is_untracked_and_counts_nowhere(): void
    {
        $case = new CaseModel;
        $case->status = CaseModel::STATUS_DOCKETED;
        $case->setRelation('timeline', null);

        $result = $this->analytics->clearanceAndDisposition([$case], $this->periodFrom(), $this->periodTo());

        $this->assertSame(1, $result['untracked']);
        $this->assertSame(0, $result['incoming']);
        $this->assertSame(0, $result['pending']);
    }

    private function caseWith(string $docketed, ?string $fir = null, string $status = CaseModel::STATUS_DOCKETED): CaseModel
    {
        $timeline = new CaseTimeline;
        $timeline->date_of_docket = CarbonImmutable::parse($docketed);
        $timeline->date_fir_submitted = $fir !== null ? CarbonImmutable::parse($fir) : null;

        $case = new CaseModel;
        $case->status = $status;
        $case->setRelation('timeline', $timeline);

        return $case;
    }
}
