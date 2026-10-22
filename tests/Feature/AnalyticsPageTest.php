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
 * CEPEJ analytics at /reports/analytics.
 *
 * Every case pins its status explicitly because CaseModelFactory randomizes it.
 * Every figure is asserted with its label so a digit in the Vite asset hash
 * cannot satisfy the assertion by accident.
 */
class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = ['from' => '2026-01-01', 'to' => '2026-06-30'];

    // ------------------------------------------------------------ access

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/reports/analytics')->assertRedirect(route('login'));
    }

    public function test_a_records_clerk_is_refused(): void
    {
        $clerk = User::factory()->withRole('Records Clerk')->create();

        $this->actingAs($clerk)->get('/reports/analytics')->assertForbidden();
    }

    public function test_an_admin_is_refused(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/reports/analytics')->assertForbidden();
    }

    // ----------------------------------------------------------- scoping

    public function test_an_investigator_sees_only_their_own_figures(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, '2026-02-01');
        $this->caseFor(null, '2026-03-01', '2026-04-01', CaseModel::STATUS_CLOSED);

        $this->actingAs($investigator)
            ->get(route('reports.analytics', self::PERIOD))
            ->assertOk()
            ->assertSeeInOrder([__('Incoming'), '1'])
            ->assertSeeInOrder([__('Resolved'), '0'])
            ->assertSeeInOrder([__('Pending at period end'), '1']);
    }

    public function test_a_supervisor_sees_the_whole_office(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->caseFor(null, '2026-02-01', '2026-03-01', CaseModel::STATUS_CLOSED);
        $this->caseFor(null, '2026-04-01');

        $this->actingAs($supervisor)
            ->get(route('reports.analytics', self::PERIOD))
            ->assertOk()
            ->assertSeeInOrder([__('Incoming'), '2'])
            ->assertSeeInOrder([__('Resolved'), '1'])
            ->assertSeeInOrder([__('Pending at period end'), '1'])
            ->assertSeeInOrder([__('Clearance Rate'), '50.0%'])
            ->assertSeeInOrder([__('Disposition Time'), __(':n days', ['n' => '181.0'])]);
    }

    // ------------------------------------------------------------ period

    public function test_a_case_docketed_before_the_period_and_resolved_inside_it_is_counted_as_resolved(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, '2025-09-01', '2026-03-15', CaseModel::STATUS_CLOSED);

        $this->actingAs($investigator)
            ->get(route('reports.analytics', self::PERIOD))
            ->assertOk()
            ->assertSeeInOrder([__('Incoming'), '0'])
            ->assertSeeInOrder([__('Resolved'), '1']);
    }

    public function test_an_open_case_carried_in_from_before_the_period_counts_as_pending(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, '2025-09-01');

        $this->actingAs($investigator)
            ->get(route('reports.analytics', self::PERIOD))
            ->assertOk()
            ->assertSeeInOrder([__('Incoming'), '0'])
            ->assertSeeInOrder([__('Pending at period end'), '1']);
    }

    public function test_undefined_rates_render_as_an_em_dash_not_zero(): void
    {
        $investigator = User::factory()->investigator()->create();

        $response = $this->actingAs($investigator)
            ->get(route('reports.analytics', self::PERIOD))
            ->assertOk()
            ->assertSeeInOrder([__('Clearance Rate'), '—', __('no intake in the period')])
            ->assertSeeInOrder([__('Disposition Time'), '—', __('nothing resolved in the period')]);

        $this->assertDoesNotMatchRegularExpression(
            '/Clearance Rate.*?stat-tile__value[^>]*>\s*0(?:\.0)?%/s',
            $response->getContent()
        );
        $this->assertDoesNotMatchRegularExpression(
            '/Disposition Time.*?stat-tile__value[^>]*>\s*0(?:\.0)?(?:\s+days)?/s',
            $response->getContent()
        );
    }

    // ---------------------------------------------------------- caveats

    public function test_untracked_and_closed_without_date_footnotes_appear_when_relevant(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, withTimeline: false);
        $this->caseFor($investigator, '2026-02-01', status: CaseModel::STATUS_CLOSED);

        $this->actingAs($investigator)
            ->get(route('reports.analytics', self::PERIOD))
            ->assertOk()
            ->assertSee('1 case has no timeline recorded and is not counted.')
            ->assertSee('1 closed case has no FIR date and is resolved but not counted in any period.');
    }

    public function test_caveat_footnotes_are_absent_when_not_relevant(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, '2026-02-01');

        $this->actingAs($investigator)
            ->get(route('reports.analytics', self::PERIOD))
            ->assertOk()
            ->assertDontSee('has no timeline recorded and is not counted')
            ->assertDontSee('has no FIR date and is resolved but not counted');
    }

    // ----------------------------------------------------------- filters

    public function test_investigator_and_status_filters_still_narrow_the_set(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $selected = User::factory()->investigator()->create();

        $this->caseFor($selected, '2026-02-01', status: CaseModel::STATUS_DOCKETED);
        $this->caseFor($selected, '2026-03-01', '2026-04-01', CaseModel::STATUS_CLOSED);
        $this->caseFor(null, '2026-05-01', status: CaseModel::STATUS_DOCKETED);

        $this->actingAs($supervisor)
            ->get(route('reports.analytics', array_merge(self::PERIOD, [
                'investigator_id' => $selected->id,
                'status' => CaseModel::STATUS_DOCKETED,
            ])))
            ->assertOk()
            ->assertSeeInOrder([__('Incoming'), '1'])
            ->assertSeeInOrder([__('Resolved'), '0'])
            ->assertSeeInOrder([__('Pending at period end'), '1']);
    }

    public function test_an_investigator_cannot_use_the_filter_to_see_a_colleagues_figures(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();

        $this->caseFor($investigator, '2026-02-01');
        $this->caseFor($colleague, '2026-03-01', '2026-04-01', CaseModel::STATUS_CLOSED);

        $this->actingAs($investigator)
            ->get(route('reports.analytics', array_merge(self::PERIOD, [
                'investigator_id' => $colleague->id,
            ])))
            ->assertOk()
            ->assertSeeInOrder([__('Incoming'), '0'])
            ->assertSeeInOrder([__('Resolved'), '0'])
            ->assertSeeInOrder([__('Pending at period end'), '0']);
    }

    public function test_only_a_supervisor_is_offered_the_investigator_filter(): void
    {
        $this->actingAs(User::factory()->supervisor()->create())
            ->get(route('reports.analytics', self::PERIOD))
            ->assertOk()
            ->assertSee(__('All investigators'));

        $this->actingAs(User::factory()->investigator()->create())
            ->get(route('reports.analytics', self::PERIOD))
            ->assertOk()
            ->assertDontSee(__('All investigators'));
    }

    // --------------------------------------------------------------- audit

    public function test_viewing_analytics_is_not_audited(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)->get(route('reports.analytics', self::PERIOD))->assertOk();

        $this->assertSame(0, AuditLog::count());
    }

    // ------------------------------------------------------------- helpers

    private function caseFor(
        ?User $investigator = null,
        string $docketedOn = '2026-03-01',
        ?string $firSubmitted = null,
        string $status = CaseModel::STATUS_DOCKETED,
        bool $withTimeline = true,
    ): CaseModel {
        $case = CaseModel::factory()
            ->assignedTo($investigator ?? User::factory()->investigator()->create())
            ->create(['status' => $status]);

        if ($withTimeline) {
            CaseTimeline::create([
                'case_id' => $case->id,
                'date_of_docket' => CarbonImmutable::parse($docketedOn),
                'date_fir_submitted' => $firSubmitted === null
                    ? null
                    : CarbonImmutable::parse($firSubmitted),
            ]);
        }

        return $case->load(['timeline', 'investigator']);
    }
}
