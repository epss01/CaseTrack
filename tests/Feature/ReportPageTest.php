<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The report listing at /reports.
 *
 * Every case pins its status explicitly, because CaseModelFactory defaults it
 * to a random pick that includes 'Closed'.
 *
 * Stat-tile figures are asserted with assertSeeInOrder([label, value]), never a
 * bare assertSee on a digit: a lone digit matches the Vite asset hash.
 */
class ReportPageTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ access

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/reports')->assertRedirect(route('login'));
    }

    public function test_a_user_with_neither_case_handling_role_is_refused(): void
    {
        $clerk = User::factory()->withRole('Records Clerk')->create();

        $this->actingAs($clerk)->get('/reports')->assertForbidden();
    }

    public function test_both_case_handling_roles_reach_the_page(): void
    {
        $this->actingAs(User::factory()->investigator()->create())->get('/reports')->assertOk();
        $this->actingAs(User::factory()->supervisor()->create())->get('/reports')->assertOk();
    }

    /**
     * The nav item follows the same rule the page does, so a user who cannot
     * reach the report is not offered a link to it.
     */
    public function test_the_nav_offers_reports_to_case_handlers_only(): void
    {
        $this->actingAs(User::factory()->investigator()->create())
            ->get('/home')
            ->assertSee(route('reports.index'), escape: false);

        $this->actingAs(User::factory()->supervisor()->create())
            ->get('/home')
            ->assertSee(route('reports.index'), escape: false);

        $this->actingAs(User::factory()->withRole('Records Clerk')->create())
            ->get('/home')
            ->assertDontSee(route('reports.index'), escape: false);
    }

    // ----------------------------------------------------------- scoping

    public function test_an_investigator_sees_only_their_own_cases(): void
    {
        $investigator = User::factory()->investigator()->create();

        $own = $this->caseFor($investigator);
        $theirs = $this->caseFor();

        $this->actingAs($investigator)
            ->get('/reports')
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

        $first = $this->caseFor();
        $second = $this->caseFor();

        $this->actingAs($supervisor)
            ->get('/reports')
            ->assertOk()
            ->assertSee($first->docket_no)
            ->assertSee($second->docket_no)
            ->assertSee($first->investigator->full_name);
    }

    /**
     * The investigator picker is a supervisor's tool. An investigator has one
     * caseload however the filter is set, so offering the choice would suggest
     * otherwise.
     */
    public function test_only_a_supervisor_is_offered_the_investigator_filter(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())
            ->get('/reports')
            ->assertSee(__('All investigators'));

        $this->actingAs(User::factory()->investigator()->create())
            ->get('/reports')
            ->assertDontSee(__('All investigators'));
    }

    // ----------------------------------------------------------- filters

    public function test_the_date_range_narrows_the_listing(): void
    {
        $investigator = User::factory()->investigator()->create();

        $inside = $this->caseFor($investigator, docketedOn: '2026-03-15');
        $outside = $this->caseFor($investigator, docketedOn: '2026-05-15');

        $this->actingAs($investigator)
            ->get(route('reports.index', ['from' => '2026-03-01', 'to' => '2026-03-31']))
            ->assertOk()
            ->assertSee($inside->docket_no)
            ->assertDontSee($outside->docket_no);
    }

    public function test_the_filter_survives_the_round_trip_into_the_form(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($supervisor)
            ->get(route('reports.index', [
                'from' => '2026-03-01',
                'to' => '2026-03-31',
                'investigator_id' => $investigator->id,
                'status' => CaseModel::STATUS_DOCKETED,
            ]))
            ->assertOk()
            ->assertSee('value="2026-03-01"', escape: false)
            ->assertSee('value="2026-03-31"', escape: false)
            ->assertSee('value="'.CaseModel::STATUS_DOCKETED.'"', escape: false)
            ->assertSee(__('Clear'));
    }

    public function test_the_download_link_carries_the_current_filter(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->get(route('reports.index', ['status' => CaseModel::STATUS_DOCKETED]))
            ->assertOk()
            ->assertSee(route('reports.export', ['status' => CaseModel::STATUS_DOCKETED]), escape: false);
    }

    public function test_a_filter_matching_nothing_says_so(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, status: CaseModel::STATUS_DOCKETED);

        $this->actingAs($investigator)
            ->get(route('reports.index', ['status' => 'Nothing has this status']))
            ->assertOk()
            ->assertSee(__('No cases match this filter.'));
    }

    // ------------------------------------------------------------- tiles

    public function test_the_tiles_describe_the_filtered_set(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, attributes: ['complexity_weight' => 4]);
        $this->caseFor($investigator, attributes: ['complexity_weight' => 3]);

        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            ->assertSeeInOrder([__('Cases in report'), '2'])
            ->assertSeeInOrder([__('Total weight'), '7']);
    }

    public function test_closed_cases_are_reported_and_deleted_ones_are_not(): void
    {
        $investigator = User::factory()->investigator()->create();

        $closed = $this->caseFor($investigator, status: CaseModel::STATUS_CLOSED);
        $deleted = $this->caseFor($investigator);
        $deleted->delete();

        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            ->assertSee($closed->docket_no)
            ->assertDontSee($deleted->docket_no)
            ->assertSeeInOrder([__('Cases in report'), '1']);
    }

    // ------------------------------------------------- the timeline caveat

    /**
     * A date range reads through to case_timelines, so a case that never got a
     * timeline row leaves the report. The page has to say so — a total that
     * quietly shrank is worse than one carrying a caveat.
     */
    public function test_a_case_excluded_by_the_date_range_for_having_no_timeline_is_reported(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, docketedOn: '2026-03-15');

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $this->actingAs($investigator)
            ->get(route('reports.index', ['from' => '2026-03-01']))
            ->assertOk()
            ->assertSeeInOrder([__('Cases in report'), '1'])
            ->assertSeeInOrder([__('Not counted'), '1'])
            ->assertSee('1 case is excluded by the date range');
    }

    public function test_no_caveat_is_shown_when_no_date_range_was_asked_for(): void
    {
        $investigator = User::factory()->investigator()->create();

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            ->assertDontSee(__('Not counted'))
            ->assertSeeInOrder([__('Cases in report'), '1']);
    }

    /**
     * The caveat counts only cases the user may see, and only those the rest of
     * the filter would have kept.
     */
    public function test_the_caveat_respects_the_other_filters(): void
    {
        $investigator = User::factory()->investigator()->create();

        // Same investigator, no timeline, but a status the filter excludes.
        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_CLOSED,
        ]);

        // Someone else's untimelined case, invisible to this investigator.
        CaseModel::factory()->create(['status' => CaseModel::STATUS_DOCKETED]);

        $this->actingAs($investigator)
            ->get(route('reports.index', ['from' => '2026-03-01', 'status' => CaseModel::STATUS_DOCKETED]))
            ->assertOk()
            ->assertDontSee(__('Not counted'));
    }

    // ------------------------------------------------------------ content

    /**
     * The listing shows the columns that stay readable at a laptop width; the
     * CSV carries all eleven. Eleven columns on screen needed 1281px inside an
     * 893px wrapper, which pushed the View button out of reach.
     */
    public function test_the_listing_shows_the_columns_that_fit(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, docketedOn: '2026-03-01');

        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            ->assertSeeInOrder([
                __('Docket No.'), __('Title'), __('Status'), __('Weight'),
                __('Docketed'), __('30-day (ROP)'), __('120-day (FIR)'),
            ])
            // Paired into the two deadline columns rather than given columns
            // of their own.
            ->assertDontSee(__('30-day Deadline'))
            ->assertDontSee(__('ROP Submitted'))
            ->assertDontSee(__('120-day Deadline'))
            ->assertDontSee(__('FIR Submitted'))
            ->assertDontSee(__('Complexity Weight'));
    }

    /**
     * The one column dropped from the screen rather than merged. It is the same
     * value on every row of a single-region deployment and already sits in the
     * identity bar — but it still has to reach anyone who exports.
     */
    public function test_office_region_is_off_the_listing_but_in_the_csv(): void
    {
        $investigator = User::factory()->investigator()->create(['office_region' => 'CHR Region VIII']);

        $this->caseFor($investigator);

        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            ->assertSee(__('included in the CSV download'), escape: false);

        $csv = $this->actingAs($investigator)
            ->get(route('reports.export'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString(__('Office Region'), $csv);
        $this->assertStringContainsString('CHR Region VIII', $csv);
    }

    /**
     * Shown only where there is more than one caseload in view, exactly as
     * /alerts does — it is a column of the same name repeated otherwise.
     */
    public function test_the_investigator_column_is_shown_only_office_wide(): void
    {
        $investigator = User::factory()->investigator()->create();
        $this->caseFor($investigator);

        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            ->assertDontSee('<th scope="col">'.__('Investigator').'</th>', escape: false);

        $this->actingAs(User::factory()->supervisor()->create())
            ->get('/reports')
            ->assertOk()
            ->assertSee('<th scope="col">'.__('Investigator').'</th>', escape: false);
    }

    public function test_each_deadline_is_shown_with_the_submission_that_discharges_it(): void
    {
        $investigator = User::factory()->investigator()->create();

        $filed = $this->caseFor($investigator, docketedOn: '2026-03-01');
        $filed->timeline->update(['date_submission_rop' => '2026-03-28']);

        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            // The 30-day deadline, then the ROP that discharged it, in one cell.
            ->assertSeeInOrder(['2026-03-31', __('filed :date', ['date' => '2026-03-28'])])
            // The FIR is outstanding, and says so rather than showing a blank.
            ->assertSee(__('not filed'));
    }

    /**
     * The button lives in the last cell of the row, which .table-data pins to
     * its own width so a long title can never squeeze it off the table.
     */
    public function test_every_row_ends_with_a_reachable_view_button(): void
    {
        $investigator = User::factory()->investigator()->create();

        $case = $this->caseFor($investigator, attributes: [
            'case_title' => 'Alleged unlawful arrest and detention of farmers during a prolonged land dispute',
        ]);

        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            ->assertSee($case->case_title)
            ->assertSeeInOrder([$case->case_title, route('reports.show', $case)], escape: false);
    }

    public function test_a_deadline_computed_by_the_service_reaches_the_page(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, docketedOn: '2026-03-01');

        // Docketed 1 March: day 30 is 31 March, day 120 is 29 June. Neither is
        // stored on the timeline, so both come from the service's fallback.
        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            ->assertSee('2026-03-31')
            ->assertSee('2026-06-29');
    }

    public function test_the_status_mix_covers_the_reported_set(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, status: CaseModel::STATUS_DOCKETED);
        $this->caseFor($investigator, status: CaseModel::STATUS_CLOSED);

        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            ->assertSee(__('Status mix'))
            ->assertSeeInOrder([CaseModel::STATUS_DOCKETED, CaseModel::STATUS_CLOSED]);
    }

    // --------------------------------------------------------------- audit

    /**
     * Viewing is not exporting. Auditing page views would fill a table that
     * currently answers "who changed this case" with traffic.
     */
    public function test_viewing_the_report_is_not_audited(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)->get('/reports')->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    // ----------------------------------------------------------------- helpers

    /**
     * A case with a timeline carrying only its date of docket.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function caseFor(
        ?User $investigator = null,
        string $docketedOn = '2026-03-01',
        string $status = CaseModel::STATUS_DOCKETED,
        array $attributes = [],
    ): CaseModel {
        $case = CaseModel::factory()
            ->assignedTo($investigator ?? User::factory()->investigator()->create())
            ->create(array_merge(['status' => $status], $attributes));

        CaseTimeline::create([
            'case_id' => $case->id,
            'date_of_docket' => CarbonImmutable::parse($docketedOn),
        ]);

        return $case->load(['timeline', 'investigator']);
    }
}
