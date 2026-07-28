<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\CaseTimelineFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The investigator dashboard at /home: an investigator's own active caseload.
 *
 * Every case here pins its status explicitly, because CaseModelFactory defaults
 * it to a random pick that includes 'Closed' — an unpinned case would drop out
 * of the active set at random.
 */
class InvestigatorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_investigator_sees_only_their_own_active_cases(): void
    {
        $investigator = User::factory()->investigator()->create();

        $ownCase = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $ownClosedCase = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_CLOSED,
        ]);

        $theirCase = CaseModel::factory()->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $this->actingAs($investigator)
            ->get('/home')
            ->assertOk()
            ->assertSee($ownCase->docket_no)
            ->assertDontSee($ownClosedCase->docket_no)
            ->assertDontSee($theirCase->docket_no);
    }

    public function test_the_summary_counts_the_active_caseload_and_pending_closures(): void
    {
        $investigator = User::factory()->investigator()->create();

        CaseModel::factory()->count(2)->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        // Pending Closure is still active, so this case counts in both figures.
        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => CaseModel::STATUS_DOCKETED,
        ]);

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_CLOSED,
        ]);

        // The figures live in the identity-bar stat tiles, whose labels are
        // rendered before their values so these assertions have a stable anchor.
        // A bare assertSee('3') would match digits in the Vite asset hash.
        $this->actingAs($investigator)
            ->get('/home')
            ->assertOk()
            ->assertSeeInOrder(['Active cases', '3'])
            ->assertSeeInOrder(['Awaiting approval', '1'])
            ->assertSeeInOrder(['Closed', '1'])
            ->assertSeeInOrder(['Total assigned', '4']);
    }

    public function test_an_investigator_with_no_active_cases_sees_the_empty_state(): void
    {
        $investigator = User::factory()->investigator()->create();

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_CLOSED,
        ]);

        $this->actingAs($investigator)
            ->get('/home')
            ->assertOk()
            ->assertSeeInOrder(['Active cases', '0'])
            ->assertSee('No active cases are assigned to you.')
            // The way through to the closed case is still offered.
            ->assertSee('View all my cases');
    }

    public function test_timeline_dates_are_shown_when_set(): void
    {
        $investigator = User::factory()->investigator()->create();

        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $docketedOn = CarbonImmutable::parse('2026-03-02');

        CaseTimeline::factory()
            ->docketedOn($docketedOn)
            ->create(['case_id' => $case->id]);

        $this->actingAs($investigator)
            ->get('/home')
            ->assertOk()
            ->assertSee($docketedOn->format('d M Y'))
            ->assertSee(
                $docketedOn->addDays(CaseTimelineFactory::HUNDRED_TWENTIETH_DAY)->format('d M Y')
            );
    }

    public function test_a_case_without_a_timeline_still_renders(): void
    {
        $investigator = User::factory()->investigator()->create();

        // Intake always writes a timeline row, but nothing in the schema forces
        // one — the dashboard must not fall over on a case that has none.
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $this->actingAs($investigator)
            ->get('/home')
            ->assertOk()
            ->assertSee($case->docket_no);
    }

    public function test_a_supervisor_gets_their_own_dashboard_instead_of_this_one(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        // /home used to redirect supervisors to the case list; it now serves the
        // office-wide dashboard. What matters here is only that they do not land
        // on the investigator page — SupervisorDashboardTest covers what they do
        // get instead.
        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertDontSee('No active cases are assigned to you.')
            ->assertDontSee('View all my cases');
    }

    public function test_a_user_without_a_case_handling_role_still_sees_the_plain_home_page(): void
    {
        $clerk = User::factory()->withRole('Records Clerk')->create();

        $this->actingAs($clerk)
            ->get('/home')
            ->assertOk()
            ->assertSee('You are logged in!')
            ->assertDontSee('Awaiting approval');
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }
}
