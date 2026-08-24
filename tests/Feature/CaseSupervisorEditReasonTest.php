<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CHR-Answers-2026-08-01, item 6: supervisor edit access exists to cover an
 * investigator on leave or handing a case over, not as general-purpose
 * access. There is no hard technical gate — the supervisor's edit rights are
 * unchanged — but editing a case they are not assigned to requires a stated
 * reason, recorded in the audit trail as ACTION_EDIT_ON_BEHALF.
 */
class CaseSupervisorEditReasonTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(CaseModel $case, array $overrides = []): array
    {
        return array_merge([
            'case_title' => 'Reviewed by supervisor',
            'incident_details' => $case->incident_details,
            'status' => $case->status,
            'complexity_weight' => $case->complexity_weight,
            'updated_at' => $case->updated_at->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    public function test_a_supervisor_editing_another_investigators_case_must_give_a_reason(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($supervisor)
            ->put(route('cases.update', $case), $this->payload($case))
            ->assertSessionHasErrors('reason');

        $this->assertNotSame('Reviewed by supervisor', $case->fresh()->case_title);
        $this->assertDatabaseMissing('audit_logs', [
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT_ON_BEHALF,
        ]);
    }

    public function test_a_supervisor_editing_another_investigators_case_succeeds_with_a_reason(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($supervisor)
            ->put(route('cases.update', $case), $this->payload($case, [
                'reason' => 'Covering while the assigned investigator is on leave.',
            ]))
            ->assertRedirect(route('cases.show', $case));

        $this->assertSame('Reviewed by supervisor', $case->fresh()->case_title);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT_ON_BEHALF,
            'notes' => 'Covering while the assigned investigator is on leave.',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT,
        ]);
    }

    public function test_an_investigator_editing_their_own_case_needs_no_reason_and_is_unaffected(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), $this->payload($case))
            ->assertRedirect(route('cases.show', $case))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $investigator->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT,
            'notes' => null,
        ]);
    }

    public function test_a_supervisor_editing_their_own_assigned_case_needs_no_reason(): void
    {
        // A supervisor is never the assigned investigator on a case in
        // practice, but the guard is investigator_id-based, not role-based —
        // prove the "self" branch of that comparison directly.
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create(['investigator_id' => $supervisor->id]);

        $this->actingAs($supervisor)
            ->put(route('cases.update', $case), $this->payload($case))
            ->assertRedirect(route('cases.show', $case))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'action_performed' => AuditLog::ACTION_EDIT,
        ]);
    }

    public function test_the_reason_field_renders_only_when_editing_another_investigators_case(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $ownCase = CaseModel::factory()->create(['investigator_id' => $supervisor->id]);
        $othersCase = CaseModel::factory()->create();

        $this->actingAs($supervisor)
            ->get(route('cases.edit', $ownCase))
            ->assertOk()
            ->assertDontSee('Reason for editing this case');

        $this->actingAs($supervisor)
            ->get(route('cases.edit', $othersCase))
            ->assertOk()
            ->assertSee('Reason for editing this case');
    }
}
