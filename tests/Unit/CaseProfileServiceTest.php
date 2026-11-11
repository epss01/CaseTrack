<?php

namespace Tests\Unit;

use App\Models\CaseTimeline;
use App\Services\CaseDeadlineService;
use App\Services\CaseProfileService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class CaseProfileServiceTest extends TestCase
{
    private function cases(array $vectors): array
    {
        $cases = [];
        foreach ($vectors as [$weight, $parties, $days]) {
            $id = count($cases) + 1;
            $cases[] = (object) ['id' => $id, 'docket_no' => "TEST-$id", 'complexity_weight' => $weight,
                'victims_count' => $parties, 'complainants_count' => 0, 'respondents_count' => 0,
                'timeline' => new CaseTimeline(['date_of_docket' => '2026-01-01',
                    'date_fir_submitted' => CarbonImmutable::parse('2026-01-01')->addDays($days)])];
        }

        return $cases;
    }

    public function test_three_groups_reproduce_memberships_means_and_constant_dimensions(): void
    {
        $vectors = [];
        foreach ([0, 10, 20] as $base) {
            foreach ([0, 0, 0, 0, 1] as $offset) {
                $vectors[] = [3, 0, $base + $offset];
            }
        }
        $cases = $this->cases($vectors);
        $service = new CaseProfileService(new CaseDeadlineService);
        $report = $service->report($cases);
        $this->assertSame($report, $service->report(array_reverse($cases)));
        $this->assertEquals([3, 0, 10.2], $report['cohort']);
        foreach ($report['profiles'] as $index => $profile) {
            $this->assertSame(range(5 * $index + 1, 5 * $index + 5), array_column($profile['members'], 'id'));
            $this->assertEquals([3, 0, 10 * $index + 0.2], $profile['means']);
        }
        $this->assertCount(3, $report['profiles']);
    }

    public function test_thresholds_and_exclusions(): void
    {
        $service = new CaseProfileService(new CaseDeadlineService);
        $cases = $this->cases(array_fill(0, 15, [3, 0, 0]));
        $this->assertStringContainsString('distinct', $service->report($cases)['reason']);
        $cases[0]->complexity_weight = 1;
        $cases[1]->complexity_weight = 5;
        $this->assertStringContainsString('fewer than three cases', $service->report($cases)['reason']);
        $cases[0]->timeline->date_of_docket = null;
        $cases[1]->timeline->date_fir_submitted = '2025-12-31';
        $cases[2]->complexity_weight = 0;
        $report = $service->report($cases);
        $this->assertSame(12, $report['eligible']);
        $this->assertSame([1, 1, 1], array_values($report['excluded']));
        $this->assertSame(15, $report['submitted']);
        $this->assertStringContainsString('15 eligible cases', $report['reason']);
    }

    public function test_symmetric_equidistant_observations_are_reproducible(): void
    {
        $points = [];
        foreach ([1, 2, 3, 4, 5] as $weight) {
            array_push($points, ...array_fill(0, 4, [$weight, 1, 1]));
        }
        $cases = $this->cases($points);
        $service = new CaseProfileService(new CaseDeadlineService);
        $report = $service->report($cases);
        $this->assertCount(3, $report['profiles']);
        $this->assertSame($report, $service->report(array_reverse($cases)));
    }
}

