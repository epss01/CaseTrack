<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CaseTimelineFactory;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CaseModel>
     */
    protected function demoCases()
    {
        return CaseModel::with(['timeline', 'complainants', 'victims', 'respondents'])
            ->where('docket_no', 'like', 'DEMO-VIII-%')
            ->get();
    }

    public function test_it_seeds_the_office_the_demo_expects(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertCount(5, User::whereRelation('role', 'role_name', Role::INVESTIGATOR)->get());
        $this->assertCount(2, User::whereRelation('role', 'role_name', Role::SUPERVISOR)->get());
        $this->assertCount(15, $this->demoCases());
    }

    public function test_every_seeded_case_is_marked_as_demo_data(): void
    {
        $this->seed(DemoDataSeeder::class);

        // No seeded case may look like a real CHR docket number.
        $this->assertSame(0, CaseModel::where('docket_no', 'not like', 'DEMO-VIII-%')->count());
    }

    public function test_every_case_has_parties_and_a_timeline(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach ($this->demoCases() as $case) {
            $this->assertNotNull($case->timeline, "{$case->docket_no} has no timeline");
            $this->assertGreaterThan(0, $case->victims->count(), "{$case->docket_no} has no victims");
            $this->assertGreaterThan(0, $case->respondents->count(), "{$case->docket_no} has no respondents");
        }
    }

    public function test_some_cases_have_no_complainant_so_the_optional_path_is_covered(): void
    {
        $this->seed(DemoDataSeeder::class);

        $withNone = $this->demoCases()->filter(fn (CaseModel $case) => $case->complainants->isEmpty());

        $this->assertGreaterThan(0, $withNone->count());
        $this->assertLessThan(15, $withNone->count());
    }

    public function test_the_monitoring_deadlines_are_derived_from_the_date_of_docket(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach ($this->demoCases() as $case) {
            $timeline = $case->timeline;
            $docketedOn = CarbonImmutable::parse($timeline->date_of_docket);

            $expected = CaseTimelineFactory::deadlinesFor($docketedOn);

            foreach (['extension_30_days', 'submission_60th_day', 'submission_120th_day', 'target_date_fir'] as $field) {
                $this->assertSame(
                    $expected[$field]->toDateString(),
                    CarbonImmutable::parse($timeline->{$field})->toDateString(),
                    "{$case->docket_no}: {$field} is not derived from the date of docket"
                );
            }
        }
    }

    public function test_the_timeline_spread_covers_every_alert_bucket(): void
    {
        $this->seed(DemoDataSeeder::class);

        $buckets = $this->demoCases()
            ->groupBy(fn (CaseModel $case) => DemoDataSeeder::bucketFor($case->timeline))
            ->map->count();

        $this->assertSame(4, $buckets['OVERDUE'] ?? 0, 'expected 4 overdue cases');
        $this->assertSame(4, $buckets['DUE SOON'] ?? 0, 'expected 4 cases due within a fortnight');
        $this->assertSame(4, $buckets['ON TRACK'] ?? 0, 'expected 4 comfortably on-track cases');
        $this->assertSame(3, $buckets['CLOSED'] ?? 0, 'expected 3 closed cases');
    }

    public function test_a_filed_submission_stops_its_deadline_counting_against_the_case(): void
    {
        $this->seed(DemoDataSeeder::class);

        // The on-track cases include one docketed well past its 30-day mark;
        // it is only on track because the ROP was actually filed.
        $ropFiledLate = $this->demoCases()->first(function (CaseModel $case) {
            $timeline = $case->timeline;

            return $timeline->date_submission_rop !== null
                && CarbonImmutable::parse($timeline->extension_30_days)->isPast()
                && DemoDataSeeder::bucketFor($timeline) === 'ON TRACK';
        });

        $this->assertNotNull(
            $ropFiledLate,
            'expected an on-track case whose 30-day mark has passed but whose ROP is filed'
        );
    }

    public function test_a_case_sits_near_its_sixtieth_day_for_the_torture_case_rule(): void
    {
        $this->seed(DemoDataSeeder::class);

        // The 60th-day RORP milestone binds torture cases only, and no
        // case-type field exists yet — so it is excluded from bucketFor().
        // One case is still seeded close to it so the rule is testable later.
        $nearSixtieth = $this->demoCases()->first(fn (CaseModel $case) => CarbonImmutable::parse(
            $case->timeline->submission_60th_day
        )->betweenIncluded(CarbonImmutable::today(), CarbonImmutable::today()->addDays(14)));

        $this->assertNotNull($nearSixtieth, 'expected a case with its 60th day inside a fortnight');
        $this->assertSame('ON TRACK', DemoDataSeeder::bucketFor($nearSixtieth->timeline));
    }

    public function test_cases_that_never_filed_an_rop_are_represented(): void
    {
        $this->seed(DemoDataSeeder::class);

        $missingRop = $this->demoCases()
            ->filter(fn (CaseModel $c) => $c->timeline->date_submission_rop === null);

        $this->assertGreaterThan(0, $missingRop->count());
    }

    public function test_the_caseload_is_deliberately_uneven(): void
    {
        $this->seed(DemoDataSeeder::class);

        $counts = $this->demoCases()
            ->groupBy('investigator_id')
            ->map->count()
            ->sortDesc()
            ->values()
            ->all();

        // Workload balancing needs something to actually balance.
        $this->assertSame([5, 4, 3, 2, 1], $counts);
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->seed(DemoDataSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertCount(15, $this->demoCases());
        $this->assertSame(7, User::count());
        $this->assertDatabaseCount('case_timelines', 15);
    }

    public function test_the_seeded_cases_render_on_the_case_profile_matrix(): void
    {
        $this->seed(DemoDataSeeder::class);

        $supervisor = User::whereRelation('role', 'role_name', Role::SUPERVISOR)->firstOrFail();
        $case = $this->demoCases()->first();

        $this->actingAs($supervisor)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee($case->docket_no)
            ->assertSee($case->case_title)
            ->assertSee($case->victims->first()->name)
            ->assertSee($case->timeline->date_of_docket->format('d M Y'));
    }
}
