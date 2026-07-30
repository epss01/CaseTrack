<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The supervisor dashboard at /home: the whole office's caseload, and every
 * closure waiting on a decision.
 *
 * The tests that carry the feature are the office-wide ones — a query that had
 * quietly picked up scopeVisibleTo() or $user->cases() would still pass a
 * single-investigator fixture, so every case here belongs to someone other than
 * the supervisor under test.
 *
 * Every case pins its status explicitly, because CaseModelFactory defaults it
 * to a random pick that includes 'Closed'.
 */
class SupervisorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_supervisor_sees_office_wide_stats(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $one = User::factory()->investigator()->create();
        $two = User::factory()->investigator()->create();

        CaseModel::factory()->count(2)->assignedTo($one)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        // Pending Closure is still active, so this counts in both figures —
        // matching scopeActive(), which excludes only Closed.
        CaseModel::factory()->assignedTo($two)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => CaseModel::STATUS_DOCKETED,
        ]);

        CaseModel::factory()->assignedTo($two)->create([
            'status' => CaseModel::STATUS_CLOSED,
        ]);

        // The labels render before their values, so these give the assertions a
        // stable anchor. A bare assertSee('3') would match the Vite asset hash.
        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSeeInOrder(['Active cases', '3'])
            ->assertSeeInOrder(['Awaiting approval', '1'])
            ->assertSeeInOrder(['Closed', '1'])
            ->assertSeeInOrder(['Total cases', '4']);
    }

    public function test_the_pending_closure_list_includes_cases_proposed_by_other_investigators(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $one = User::factory()->investigator()->create();
        $two = User::factory()->investigator()->create();

        // Two different caseloads, neither the supervisor's own. This is the
        // check that a scoped query would fail.
        $first = CaseModel::factory()->assignedTo($one)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        $second = CaseModel::factory()->assignedTo($two)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'For review',
        ]);

        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSee($first->docket_no)
            ->assertSee($second->docket_no)
            // Each row offers the decision, and names where a rejection sends it.
            ->assertSee(route('cases.closure.review', $first))
            ->assertSee(route('cases.closure.review', $second))
            ->assertSee('Under investigation')
            ->assertSee('For review');
    }

    public function test_the_pending_list_holds_only_proposed_closures(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create();

        $pending = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => CaseModel::STATUS_DOCKETED,
        ]);

        $open = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $closed = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_CLOSED,
        ]);

        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSee($pending->docket_no)
            // A decision queue that lists cases needing no decision is not one.
            ->assertDontSee($open->docket_no)
            ->assertDontSee($closed->docket_no);
    }

    public function test_a_deleted_case_counts_nowhere(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create();

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $deleted = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => CaseModel::STATUS_DOCKETED,
        ]);
        $deleted->delete();

        // SoftDeletes scopes both queries, so a soft-deleted case leaves the
        // tiles and the queue together.
        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSeeInOrder(['Active cases', '1'])
            ->assertSeeInOrder(['Awaiting approval', '0'])
            ->assertSeeInOrder(['Total cases', '1'])
            ->assertDontSee($deleted->docket_no);
    }

    public function test_a_supervisor_with_nothing_pending_sees_the_empty_state(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        CaseModel::factory()->create(['status' => CaseModel::STATUS_DOCKETED]);

        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSeeInOrder(['Awaiting approval', '0'])
            ->assertSee('No closures are waiting on a decision.')
            // The way through to the full list is still offered.
            ->assertSee('View all cases in the office');
    }

    public function test_an_office_with_no_cases_at_all_still_renders(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        // The status bar is skipped entirely when there is no mix to show, and
        // max($total, 1) keeps the widths off a division by zero either way.
        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSeeInOrder(['Active cases', '0'])
            ->assertSeeInOrder(['Total cases', '0'])
            ->assertSee('No closures are waiting on a decision.');
    }

    public function test_the_dashboard_links_to_the_workload_page_rather_than_restating_it(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create([
            'first_name' => 'Ana Marie',
            'last_name' => 'Bautista',
            'performance_rating' => 0.5,
        ]);

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
            'complexity_weight' => 4,
        ]);

        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSee(route('workload.index'))
            // WCS stays on the page that owns it: 4 x (2 - 0.5) = 6.0 belongs to
            // /workload, and two places computing it is what the link avoids.
            ->assertDontSee('6.0')
            ->assertDontSee('Ana Marie Bautista');
    }

    public function test_an_investigator_does_not_get_the_supervisor_dashboard(): void
    {
        $investigator = User::factory()->investigator()->create();

        CaseModel::factory()->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => CaseModel::STATUS_DOCKETED,
        ]);

        // Same URL, no route parameter, and nothing on it to tamper with — the
        // branch is the only thing standing between the two pages.
        $this->actingAs($investigator)
            ->get('/home')
            ->assertOk()
            ->assertDontSee('Awaiting Your Decision')
            ->assertDontSee('Office Caseload');
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }
}
