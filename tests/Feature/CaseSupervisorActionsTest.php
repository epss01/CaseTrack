<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deletion and reassignment: supervisor-only, and both audited.
 */
class CaseSupervisorActionsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------- delete

    public function test_a_supervisor_is_asked_to_confirm_before_deleting(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($supervisor)
            ->get(route('cases.confirm-delete', $case))
            ->assertOk()
            ->assertSee('Delete Case')
            ->assertSee($case->docket_no);

        // Reaching the confirmation page must not delete anything by itself.
        $this->assertNotSoftDeleted($case);
    }

    public function test_a_supervisor_can_delete_a_case_and_it_is_audited(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($supervisor)
            ->delete(route('cases.destroy', $case))
            ->assertRedirect(route('cases.index'));

        $this->assertSoftDeleted('cases', ['id' => $case->id]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_DELETE,
        ]);
    }

    public function test_the_delete_audit_entry_keeps_a_resolvable_case_id(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($supervisor)->delete(route('cases.destroy', $case));

        $entry = AuditLog::where('action_performed', AuditLog::ACTION_DELETE)->firstOrFail();

        // The whole point of soft-deleting: case_id does not go null, and the
        // case behind it can still be reached with the trashed scope.
        $this->assertSame($case->id, $entry->case_id);
        $this->assertSame($case->docket_no, CaseModel::withTrashed()->find($entry->case_id)->docket_no);
    }

    public function test_a_deleted_case_drops_off_the_listing_and_the_matrix(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($supervisor)->delete(route('cases.destroy', $case));

        // The first load still carries the "Case ... deleted." flash, which
        // quotes the docket number; the listing underneath is already empty.
        $this->actingAs($supervisor)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee('No cases to show.');

        $this->actingAs($supervisor)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertDontSee($case->docket_no);

        $this->actingAs($supervisor)
            ->get("/cases/{$case->id}")
            ->assertNotFound();
    }

    public function test_an_investigator_cannot_delete_even_their_own_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)
            ->get("/cases/{$case->id}/delete")
            ->assertForbidden();

        $this->actingAs($investigator)
            ->delete("/cases/{$case->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted($case);
        $this->assertDatabaseMissing('audit_logs', ['action_performed' => AuditLog::ACTION_DELETE]);
        $this->assertSame(2, AuditLog::where('user_id', $investigator->id)
            ->where('case_id', $case->id)
            ->where('action_performed', AuditLog::ACTION_ACCESS_DENIED)
            ->count());
    }

    public function test_the_delete_control_is_hidden_from_investigators(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertDontSee(route('cases.confirm-delete', $case))
            ->assertDontSee(route('cases.reassign.edit', $case));
    }

    // ------------------------------------------------------------ reassign

    public function test_a_supervisor_can_reassign_a_case_and_it_is_audited(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $original = User::factory()->investigator()->create();
        $replacement = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($original)->create(['docket_no' => 'CHR-VIII-2026-0400']);

        $this->actingAs($supervisor)
            ->get(route('cases.reassign.edit', $case))
            ->assertOk()
            ->assertSee('Reassign Case')
            ->assertSee($replacement->full_name)
            // Reassignment is where the Workload Capacity Score is meant to
            // guide the choice, so the picker has to show it.
            ->assertSee('WCS')
            ->assertSee('(suggested)');

        $this->actingAs($supervisor)
            ->put(route('cases.reassign.update', $case), [
                'investigator_id' => $replacement->id,
                'docket_no' => 'CHR-VIII-2026-0401',
            ])
            ->assertRedirect(route('cases.show', $case))
            ->assertSessionHasNoErrors();

        $case->refresh();

        $this->assertSame($replacement->id, $case->investigator_id);
        $this->assertSame('CHR-VIII-2026-0401', $case->docket_no);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_UPDATE,
        ]);
    }

    public function test_reassignment_may_keep_the_existing_docket_number(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $replacement = User::factory()->investigator()->create();
        $case = CaseModel::factory()->create(['docket_no' => 'CHR-VIII-2026-0400']);

        $this->actingAs($supervisor)
            ->put(route('cases.reassign.update', $case), [
                'investigator_id' => $replacement->id,
                'docket_no' => 'CHR-VIII-2026-0400',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($replacement->id, $case->fresh()->investigator_id);
    }

    public function test_a_case_cannot_be_reassigned_onto_another_cases_docket_number(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $replacement = User::factory()->investigator()->create();
        CaseModel::factory()->create(['docket_no' => 'CHR-VIII-2026-0500']);
        $case = CaseModel::factory()->create(['docket_no' => 'CHR-VIII-2026-0400']);

        $this->actingAs($supervisor)
            ->put(route('cases.reassign.update', $case), [
                'investigator_id' => $replacement->id,
                'docket_no' => 'CHR-VIII-2026-0500',
            ])
            ->assertSessionHasErrors('docket_no');

        $this->assertSame('CHR-VIII-2026-0400', $case->fresh()->docket_no);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_case_cannot_be_reassigned_to_a_supervisor(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $anotherSupervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($supervisor)
            ->put(route('cases.reassign.update', $case), [
                'investigator_id' => $anotherSupervisor->id,
                'docket_no' => $case->docket_no,
            ])
            ->assertSessionHasErrors('investigator_id');

        $this->assertNotSame($anotherSupervisor->id, $case->fresh()->investigator_id);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_investigator_cannot_reassign_even_their_own_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)
            ->get("/cases/{$case->id}/reassign")
            ->assertForbidden();

        $this->actingAs($investigator)
            ->put("/cases/{$case->id}/reassign", [
                'investigator_id' => $colleague->id,
                'docket_no' => 'CHR-VIII-2026-9999',
            ])
            ->assertForbidden();

        $case->refresh();

        $this->assertSame($investigator->id, $case->investigator_id);
        $this->assertDatabaseMissing('audit_logs', ['action_performed' => AuditLog::ACTION_UPDATE]);
        $this->assertSame(2, AuditLog::where('user_id', $investigator->id)
            ->where('case_id', $case->id)
            ->where('action_performed', AuditLog::ACTION_ACCESS_DENIED)
            ->count());
    }

    public function test_the_ordinary_edit_form_still_cannot_move_a_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create(['docket_no' => 'CHR-VIII-2026-0400']);

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), [
                'case_title' => 'Updated title',
                'incident_details' => 'Updated details',
                'status' => 'Under investigation',
                'complexity_weight' => 2,
                'investigator_id' => $colleague->id,
                'docket_no' => 'CHR-VIII-2026-9999',
                'updated_at' => $case->updated_at->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('cases.show', $case));

        $case->refresh();

        $this->assertSame($investigator->id, $case->investigator_id);
        $this->assertSame('CHR-VIII-2026-0400', $case->docket_no);
    }

    public function test_an_ordinary_edit_is_audited(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create(['status' => 'Docketed']);

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), [
                'case_title' => 'Corrected title',
                'incident_details' => 'Corrected details',
                'status' => 'Under investigation',
                'complexity_weight' => 4,
                'updated_at' => $case->updated_at->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('cases.show', $case))
            ->assertSessionHasNoErrors();

        $this->assertSame('Corrected title', $case->fresh()->case_title);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $investigator->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT,
        ]);
    }

    /**
     * The two acts stay apart in the trail. Collapsing ACTION_EDIT back into
     * ACTION_UPDATE would leave "the title was corrected" and "the case
     * changed hands" reading identically.
     */
    public function test_an_edit_and_a_reassignment_are_not_the_same_entry(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $replacement = User::factory()->investigator()->create();
        $case = CaseModel::factory()->create(['docket_no' => 'CHR-VIII-2026-0400']);

        $this->actingAs($supervisor)
            ->put(route('cases.reassign.update', $case), [
                'investigator_id' => $replacement->id,
                'docket_no' => 'CHR-VIII-2026-0400',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', ['action_performed' => AuditLog::ACTION_UPDATE]);
        $this->assertDatabaseMissing('audit_logs', ['action_performed' => AuditLog::ACTION_EDIT]);
    }

    public function test_a_rejected_edit_writes_no_audit_entry(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create(['case_title' => 'Original title']);

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), [
                'case_title' => '',
                'incident_details' => 'Corrected details',
                'status' => 'Under investigation',
                'complexity_weight' => 4,
            ])
            ->assertSessionHasErrors('case_title');

        $this->assertSame('Original title', $case->fresh()->case_title);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    // ------------------------------------------------------------------ misc

    public function test_a_user_without_a_case_handling_role_is_blocked_by_middleware(): void
    {
        $outsider = User::factory()->withRole('Records Clerk')->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($outsider)->get("/cases/{$case->id}/delete")->assertForbidden();
        $this->actingAs($outsider)->delete("/cases/{$case->id}")->assertForbidden();
        $this->actingAs($outsider)->get("/cases/{$case->id}/reassign")->assertForbidden();

        $this->assertNotSoftDeleted($case);
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $case = CaseModel::factory()->create();

        $this->get(route('cases.confirm-delete', $case))->assertRedirect('/login');
        $this->delete(route('cases.destroy', $case))->assertRedirect('/login');
        $this->get(route('cases.reassign.edit', $case))->assertRedirect('/login');

        $this->assertNotSoftDeleted($case);
    }
}
