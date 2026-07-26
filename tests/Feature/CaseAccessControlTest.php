<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseAccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_investigator_can_view_a_case_assigned_to_them(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee($case->docket_no);
    }

    public function test_an_investigator_cannot_view_another_investigators_case_by_direct_url(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();
        $theirCase = CaseModel::factory()->assignedTo($colleague)->create();

        $this->actingAs($investigator)
            ->get("/cases/{$theirCase->id}")
            ->assertForbidden()
            ->assertDontSee($theirCase->docket_no);
    }

    public function test_an_investigator_cannot_edit_or_update_another_investigators_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();
        $theirCase = CaseModel::factory()->assignedTo($colleague)->create(['status' => 'Docketed']);

        $this->actingAs($investigator)
            ->get("/cases/{$theirCase->id}/edit")
            ->assertForbidden();

        $this->actingAs($investigator)
            ->put("/cases/{$theirCase->id}", [
                'case_title' => 'Hijacked',
                'incident_details' => 'Hijacked',
                'status' => 'Closed',
                'complexity_weight' => 1,
            ])
            ->assertForbidden();

        $this->assertSame('Docketed', $theirCase->fresh()->status);
    }

    public function test_an_investigator_only_sees_their_own_cases_in_the_listing(): void
    {
        $investigator = User::factory()->investigator()->create();
        $ownCase = CaseModel::factory()->assignedTo($investigator)->create();
        $otherCase = CaseModel::factory()->create();

        $this->actingAs($investigator)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee($ownCase->docket_no)
            ->assertDontSee($otherCase->docket_no);
    }

    public function test_an_investigator_can_update_their_own_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), [
                'case_title' => 'Updated title',
                'incident_details' => 'Updated details',
                'source_info' => 'Walk-in',
                'status' => 'Under investigation',
                'complexity_weight' => 3,
            ])
            ->assertRedirect(route('cases.show', $case));

        $this->assertSame('Updated title', $case->fresh()->case_title);
    }

    public function test_a_supervisor_can_view_and_edit_any_case_in_the_office(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($supervisor)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee($case->docket_no);

        $this->actingAs($supervisor)
            ->put(route('cases.update', $case), [
                'case_title' => 'Reviewed by supervisor',
                'incident_details' => $case->incident_details,
                'status' => 'For review',
                'complexity_weight' => $case->complexity_weight,
            ])
            ->assertRedirect(route('cases.show', $case));

        $this->assertSame('Reviewed by supervisor', $case->fresh()->case_title);
    }

    public function test_a_supervisor_sees_every_investigators_cases_in_the_listing(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $first = CaseModel::factory()->create();
        $second = CaseModel::factory()->create();

        $this->actingAs($supervisor)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee($first->docket_no)
            ->assertSee($second->docket_no);
    }

    public function test_a_user_without_a_case_handling_role_is_blocked_by_middleware(): void
    {
        $outsider = User::factory()->withRole('Records Clerk')->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($outsider)->get(route('cases.index'))->assertForbidden();
        $this->actingAs($outsider)->get(route('cases.show', $case))->assertForbidden();
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $case = CaseModel::factory()->create();

        $this->get(route('cases.index'))->assertRedirect('/login');
        $this->get(route('cases.show', $case))->assertRedirect('/login');
    }

    public function test_the_policy_is_discovered_for_the_case_model(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $colleague = User::factory()->investigator()->create();
        $supervisor = User::factory()->supervisor()->create();

        $this->assertTrue($investigator->can('view', $case));
        $this->assertTrue($investigator->can('update', $case));
        $this->assertFalse($colleague->can('view', $case));
        $this->assertFalse($colleague->can('update', $case));
        $this->assertTrue($supervisor->can('view', $case));

        // Both roles run intake.
        $this->assertTrue($investigator->can('create', CaseModel::class));
        $this->assertTrue($supervisor->can('create', CaseModel::class));
        $this->assertFalse(User::factory()->withRole('Records Clerk')->create()->can('create', CaseModel::class));

        // Deleting and reassigning are supervisory, never casework — an
        // investigator is denied even on the case assigned to them.
        $this->assertTrue($supervisor->can('delete', $case));
        $this->assertTrue($supervisor->can('reassign', $case));
        $this->assertFalse($investigator->can('delete', $case));
        $this->assertFalse($investigator->can('reassign', $case));
    }

    public function test_role_names_are_the_two_the_office_uses(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->assertSame(
            [Role::INVESTIGATOR, Role::SUPERVISOR],
            Role::orderBy('role_name')->pluck('role_name')->all()
        );
    }
}
