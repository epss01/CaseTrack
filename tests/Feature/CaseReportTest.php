<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\Complainant;
use App\Models\Respondent;
use App\Models\User;
use App\Models\Victim;
use App\Services\CaseDeadlineService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The per-case report at /reports/cases/{case}.
 *
 * The one report that resolves a single case, and so the one gated by
 * CaseModelPolicy::view() on top of the role middleware. Most of these tests
 * exist to prove that gate holds, because it is the only thing standing between
 * an investigator and a colleague's case.
 */
class CaseReportTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ access

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $case = $this->caseFor();

        $this->get(route('reports.show', $case))->assertRedirect(route('login'));
    }

    public function test_a_user_with_neither_case_handling_role_is_refused(): void
    {
        $clerk = User::factory()->withRole('Records Clerk')->create();
        $case = $this->caseFor();

        $this->actingAs($clerk)->get(route('reports.show', $case))->assertForbidden();
    }

    public function test_an_investigator_reads_their_own_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = $this->caseFor($investigator);

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertSee($case->docket_no);
    }

    /**
     * The check the whole route exists for. The role middleware would let this
     * request through — only the policy stops it.
     */
    public function test_an_investigator_cannot_read_a_colleagues_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $theirs = $this->caseFor();

        $this->actingAs($investigator)
            ->get(route('reports.show', $theirs))
            ->assertForbidden();
    }

    public function test_a_supervisor_reads_any_case_in_the_office(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = $this->caseFor();

        $this->actingAs($supervisor)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertSee($case->docket_no);
    }

    public function test_a_deleted_case_has_no_report(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = $this->caseFor($investigator);
        $case->delete();

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertNotFound();
    }

    // ------------------------------------------------------------ content

    public function test_the_report_carries_the_same_fields_as_the_listing(): void
    {
        $investigator = User::factory()->investigator()->create();

        $case = $this->caseFor($investigator, docketedOn: '2026-03-01', attributes: [
            'case_title' => 'Alleged unlawful arrest',
            'complexity_weight' => 4,
        ]);

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertSeeInOrder([
                __('Docket No.'), __('Title'), __('Status'), __('Investigator'),
                __('Office Region'), __('Complexity Weight'), __('Date of Docket'),
                __('30-day Deadline'), __('ROP Submitted'), __('120-day Deadline'),
                __('FIR Submitted'),
            ])
            ->assertSee('Alleged unlawful arrest')
            ->assertSee($investigator->full_name);
    }

    public function test_the_statutory_deadlines_are_shown_with_their_alert_state(): void
    {
        $investigator = User::factory()->investigator()->create();

        // Docketed 40 days ago with no ROP filed: the 30-day mark has passed.
        $case = $this->caseFor($investigator, docketedOn: CarbonImmutable::today()->subDays(40)->toDateString());

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertSee(__('Statutory deadlines'))
            ->assertSee('30-day (ROP)')
            ->assertSee(__('Overdue'))
            ->assertSee('10 days late');
    }

    public function test_a_filed_fir_discharges_every_milestone(): void
    {
        $investigator = User::factory()->investigator()->create();

        $case = $this->caseFor($investigator, docketedOn: CarbonImmutable::today()->subDays(300)->toDateString());
        $case->timeline->update(['date_fir_submitted' => CarbonImmutable::today()->subDays(5)]);

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertSee(__('Submitted'))
            ->assertDontSee(__('Overdue'));
    }

    /**
     * No timeline row is not the same as being on track, and must not read as
     * it — the same distinction /alerts draws with its untracked count.
     */
    public function test_a_case_with_no_timeline_says_it_cannot_be_measured(): void
    {
        $investigator = User::factory()->investigator()->create();

        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertSee('no deadline can be measured')
            ->assertDontSee(__('On track'));
    }

    /**
     * The 60th day binds only torture cases and no case-type column exists, so
     * CaseDeadlineService gates it out of everything a user can see.
     */
    public function test_the_sixtieth_day_appears_nowhere(): void
    {
        if (CaseDeadlineService::SIXTY_DAY_ENABLED) {
            $this->markTestSkipped('The 60-day milestone has been enabled; this gate no longer applies.');
        }

        $investigator = User::factory()->investigator()->create();
        $case = $this->caseFor($investigator, docketedOn: '2026-01-01');

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertDontSee('60th day')
            ->assertDontSee('RORP');
    }

    // ------------------------------------------------------------ parties

    public function test_the_parties_are_listed_with_their_counts(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = $this->caseFor($investigator);

        Complainant::create(['case_id' => $case->id, 'name' => 'Perpetua Sarmiento']);
        Victim::create([
            'case_id' => $case->id,
            'name' => 'Bayani Delos Reyes',
            'age' => 34,
            'status' => 'Detained',
            'sector' => 'Farmer',
        ]);
        Respondent::create(['case_id' => $case->id, 'name' => 'Unidentified officer']);

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertSee('Perpetua Sarmiento')
            ->assertSee('Bayani Delos Reyes')
            ->assertSee('Detained')
            ->assertSee('Farmer')
            ->assertSee('Unidentified officer')
            // A respondent recorded with no status or sector says so rather
            // than rendering an empty gap.
            ->assertSee(__('status not recorded'));
    }

    /**
     * A complainant has no status, sector or age column at all — that is not
     * the same as having them empty, and the report should not invent them.
     */
    public function test_a_complainant_is_not_given_fields_it_has_no_columns_for(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = $this->caseFor($investigator);

        Complainant::create(['case_id' => $case->id, 'name' => 'Perpetua Sarmiento']);

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertSee('Perpetua Sarmiento')
            ->assertDontSee(__('status not recorded'));
    }

    public function test_a_case_with_no_parties_says_so(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = $this->caseFor($investigator);

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertSee(__('None recorded.'));
    }

    // ------------------------------------------------------------- links

    public function test_the_listing_links_into_the_report_and_the_report_into_the_profile(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = $this->caseFor($investigator);

        $this->actingAs($investigator)
            ->get('/reports')
            ->assertOk()
            ->assertSee(route('reports.show', $case), escape: false);

        $this->actingAs($investigator)
            ->get(route('reports.show', $case))
            ->assertOk()
            ->assertSee(route('cases.show', $case), escape: false);
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
