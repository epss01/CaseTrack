<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\User;
use App\Services\CaseDeadlineService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The deadline alerts page at /alerts.
 *
 * Every case pins its status explicitly, because CaseModelFactory defaults it to
 * a random pick that includes 'Closed' — an unpinned case would drop out of the
 * active set at random. Timelines are built by hand rather than through the
 * factory so each fixture states the one date the test is about.
 *
 * Stat-tile figures are asserted with assertSeeInOrder([label, value]), never a
 * bare assertSee on a digit: a lone digit matches the Vite asset hash.
 */
class DeadlineAlertsPageTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ access

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/alerts')->assertRedirect(route('login'));
    }

    public function test_a_user_with_neither_case_handling_role_is_refused(): void
    {
        $clerk = User::factory()->withRole('Records Clerk')->create();

        $this->actingAs($clerk)->get('/alerts')->assertForbidden();
    }

    // ----------------------------------------------------------- scoping

    public function test_an_investigator_sees_only_their_own_overdue_cases(): void
    {
        $investigator = User::factory()->investigator()->create();

        $own = $this->overdueCase(assignedTo: $investigator);
        $theirs = $this->overdueCase();

        $this->actingAs($investigator)
            ->get('/alerts')
            ->assertOk()
            ->assertSee($own->docket_no)
            ->assertDontSee($theirs->docket_no);
    }

    /**
     * Every fixture belongs to a different investigator, so a query that had
     * accidentally scoped itself to the supervisor's own cases would fail here.
     */
    public function test_a_supervisor_sees_the_whole_office(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $first = $this->overdueCase();
        $second = $this->dueSoonCase();

        $this->actingAs($supervisor)
            ->get('/alerts')
            ->assertOk()
            ->assertSee($first->docket_no)
            ->assertSee($second->docket_no)
            ->assertSee($first->investigator->full_name);
    }

    public function test_closed_and_deleted_cases_appear_nowhere(): void
    {
        $investigator = User::factory()->investigator()->create();

        $closed = $this->overdueCase(assignedTo: $investigator, status: CaseModel::STATUS_CLOSED);
        $deleted = $this->overdueCase(assignedTo: $investigator);
        $deleted->delete();

        $this->actingAs($investigator)
            ->get('/alerts')
            ->assertOk()
            ->assertDontSee($closed->docket_no)
            ->assertDontSee($deleted->docket_no)
            ->assertSeeInOrder([__('Overdue'), '0'])
            ->assertSeeInOrder([__('Cases tracked'), '0']);
    }

    // ------------------------------------------------------------ buckets

    public function test_the_tiles_count_each_bucket(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->overdueCase(assignedTo: $investigator);
        $this->overdueCase(assignedTo: $investigator);
        $this->dueSoonCase(assignedTo: $investigator);
        $this->onTrackCase(assignedTo: $investigator);

        $this->actingAs($investigator)
            ->get('/alerts')
            ->assertOk()
            ->assertSeeInOrder([__('Overdue'), '2'])
            ->assertSeeInOrder([__('Due soon'), '1'])
            ->assertSeeInOrder([__('On track'), '1'])
            ->assertSeeInOrder([__('Cases tracked'), '4']);
    }

    public function test_an_on_track_case_is_counted_but_kept_off_the_work_queue(): void
    {
        $investigator = User::factory()->investigator()->create();

        $onTrack = $this->onTrackCase(assignedTo: $investigator);

        $this->actingAs($investigator)
            ->get('/alerts')
            ->assertOk()
            ->assertSeeInOrder([__('On track'), '1'])
            ->assertSee(__('No deadline needs attention.'))
            ->assertDontSee($onTrack->docket_no);
    }

    public function test_the_most_overdue_case_is_listed_first(): void
    {
        $investigator = User::factory()->investigator()->create();

        $slightly = $this->overdueCase(assignedTo: $investigator, docketedDaysAgo: 35);
        $badly = $this->overdueCase(assignedTo: $investigator, docketedDaysAgo: 200);

        $this->actingAs($investigator)
            ->get('/alerts')
            ->assertOk()
            ->assertSeeInOrder([$badly->docket_no, $slightly->docket_no]);
    }

    public function test_a_case_with_no_timeline_is_reported_as_untrackable_rather_than_fine(): void
    {
        $investigator = User::factory()->investigator()->create();

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $this->actingAs($investigator)
            ->get('/alerts')
            ->assertOk()
            ->assertSeeInOrder([__('Cases tracked'), '0'])
            ->assertSee('1 active case has no timeline recorded');
    }

    public function test_a_filed_fir_takes_a_long_overdue_case_off_the_queue(): void
    {
        $investigator = User::factory()->investigator()->create();

        $case = $this->overdueCase(assignedTo: $investigator, docketedDaysAgo: 300);
        $case->timeline->update(['date_fir_submitted' => CarbonImmutable::today()->subDays(5)]);

        $this->actingAs($investigator)
            ->get('/alerts')
            ->assertOk()
            ->assertDontSee($case->docket_no)
            ->assertSeeInOrder([__('Overdue'), '0'])
            ->assertSeeInOrder([__('On track'), '1']);
    }

    // ------------------------------------------------------- the 60-day gate

    /**
     * A case 90 days in with its ROP filed: the 60th day passed a month ago and
     * is the only milestone that could put it on this page. While the flag is
     * off, nothing about it may reach the response — not the count, not the
     * label, not the column name.
     */
    public function test_a_case_well_past_its_sixtieth_day_shows_no_trace_of_that_milestone(): void
    {
        if (CaseDeadlineService::SIXTY_DAY_ENABLED) {
            $this->markTestSkipped('The 60-day milestone has been enabled; this gate no longer applies.');
        }

        $investigator = User::factory()->investigator()->create();

        $docketedDaysAgo = 90;

        $case = $this->caseWithTimeline($investigator, $docketedDaysAgo, [
            'date_submission_rop' => CarbonImmutable::today()->subDays(70),
        ]);

        // Without this the test would pass on a fixture that was never overdue
        // in the first place. The 60th day is a month behind us: gate it wrongly
        // and this case is top of the queue.
        $this->assertTrue(
            CaseDeadlineService::deadlineOn(
                CarbonImmutable::today()->subDays($docketedDaysAgo),
                CaseDeadlineService::SIXTIETH_DAY
            )->isPast(),
            'The fixture is not actually past its 60th day.'
        );

        $response = $this->actingAs($investigator)->get('/alerts')->assertOk();

        $response->assertDontSee($case->docket_no)
            ->assertDontSee('60th day')
            ->assertDontSee('RORP')
            ->assertDontSee('submission_60th_day')
            ->assertSeeInOrder([__('Overdue'), '0'])
            ->assertSeeInOrder([__('On track'), '1']);
    }

    // --------------------------------------------------------- pagination

    public function test_the_listing_paginates_at_fifteen_while_the_tiles_still_count_everything(): void
    {
        $investigator = User::factory()->investigator()->create();

        // 16 overdue cases, each a day more overdue than the last, so the
        // worst-first sort is deterministic across both pages.
        $cases = [];
        for ($i = 0; $i < 16; $i++) {
            $cases[] = $this->overdueCase(assignedTo: $investigator, docketedDaysAgo: 40 + $i);
        }

        $mostOverdue = $cases[15];
        $leastOverdue = $cases[0];

        $this->actingAs($investigator)
            ->get(route('alerts.index'))
            ->assertOk()
            ->assertSee($mostOverdue->docket_no)
            ->assertDontSee($leastOverdue->docket_no)
            ->assertSeeInOrder([__('Cases tracked'), '16']);

        $this->actingAs($investigator)
            ->get(route('alerts.index', ['page' => 2]))
            ->assertOk()
            ->assertSee($leastOverdue->docket_no)
            ->assertDontSee($mostOverdue->docket_no)
            ->assertSeeInOrder([__('Cases tracked'), '16']);
    }

    // ----------------------------------------------------------------- helpers

    /**
     * A case with a timeline whose only dates are the ones given here.
     *
     * Deliberately not CaseTimelineFactory: that fills every derived column, and
     * these fixtures are about one date at a time.
     */
    private function caseWithTimeline(
        User $investigator,
        int $docketedDaysAgo,
        array $dates = [],
        string $status = CaseModel::STATUS_DOCKETED,
    ): CaseModel {
        $case = CaseModel::factory()->assignedTo($investigator)->create(['status' => $status]);

        CaseTimeline::create(array_merge([
            'case_id' => $case->id,
            'date_of_docket' => CarbonImmutable::today()->subDays($docketedDaysAgo),
        ], $dates));

        return $case->load('timeline');
    }

    /**
     * Past the 30-day mark with no ROP filed.
     */
    private function overdueCase(
        ?User $assignedTo = null,
        int $docketedDaysAgo = 40,
        string $status = CaseModel::STATUS_DOCKETED,
    ): CaseModel {
        return $this->caseWithTimeline(
            $assignedTo ?? User::factory()->investigator()->create(),
            $docketedDaysAgo,
            status: $status,
        );
    }

    /**
     * Inside the warning window on the 30-day mark, not past it.
     */
    private function dueSoonCase(?User $assignedTo = null): CaseModel
    {
        return $this->caseWithTimeline(
            $assignedTo ?? User::factory()->investigator()->create(),
            CaseDeadlineService::EXTENSION_DAYS - CaseDeadlineService::WARNING_WINDOW_DAYS,
        );
    }

    /**
     * Freshly docketed, so every milestone is beyond the warning window.
     */
    private function onTrackCase(?User $assignedTo = null): CaseModel
    {
        return $this->caseWithTimeline(
            $assignedTo ?? User::factory()->investigator()->create(),
            1,
        );
    }
}
