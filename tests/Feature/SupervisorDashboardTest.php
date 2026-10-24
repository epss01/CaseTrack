<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_supervisor_sees_office_wide_three_tile_stats(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $one = User::factory()->investigator()->create();
        $two = User::factory()->investigator()->create();

        CaseModel::factory()->count(2)->assignedTo($one)->create(['status' => CaseModel::STATUS_DOCKETED]);
        CaseModel::factory()->assignedTo($two)->create(['status' => CaseModel::STATUS_FOR_REVIEW]);
        CaseModel::factory()->assignedTo($two)->create(['status' => CaseModel::STATUS_CLOSED]);

        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSeeInOrder(['Active cases', '3'])
            ->assertSeeInOrder(['Closed', '1'])
            ->assertSeeInOrder(['Total cases', '4'])
            ->assertSee('row-cols-1 row-cols-md-3', false)
            ->assertDontSee('Awaiting approval')
            ->assertDontSee('Awaiting Your Decision');
    }

    public function test_the_dashboard_is_status_monitoring_only(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create();
        CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_UNDER_INVESTIGATION]);

        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSee('Office caseload by status')
            ->assertSee(CaseModel::STATUS_UNDER_INVESTIGATION)
            ->assertSee(route('cases.index'))
            ->assertSee(route('workload.index'))
            ->assertDontSee('Review Closure')
            ->assertDontSee('No closures are waiting on a decision.');
    }

    public function test_a_deleted_case_counts_nowhere(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create();
        CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_DOCKETED]);
        $deleted = CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_FOR_REVIEW]);
        $deleted->delete();

        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSeeInOrder(['Active cases', '1'])
            ->assertSeeInOrder(['Total cases', '1'])
            ->assertDontSee($deleted->docket_no);
    }

    public function test_an_office_with_no_cases_still_renders(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)
            ->get('/home')
            ->assertOk()
            ->assertSeeInOrder(['Active cases', '0'])
            ->assertSeeInOrder(['Closed', '0'])
            ->assertSeeInOrder(['Total cases', '0']);
    }

    public function test_an_investigator_does_not_get_the_supervisor_dashboard(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->get('/home')
            ->assertOk()
            ->assertDontSee('Office Caseload');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/home')->assertRedirect('/login');
    }
}
