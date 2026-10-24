<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CaseDirectClosureTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(CaseModel $case, array $overrides = []): array
    {
        return array_merge([
            'case_title' => $case->case_title,
            'incident_details' => $case->incident_details,
            'source_info' => $case->source_info,
            'status' => $case->status,
            'complexity_weight' => $case->complexity_weight,
            'updated_at' => $case->updated_at->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    public function test_the_assigned_investigator_can_close_their_case_with_both_audits(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_FOR_REVIEW,
        ]);

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), $this->payload($case, [
                'status' => CaseModel::STATUS_CLOSED,
            ]))
            ->assertRedirect(route('cases.show', $case));

        $this->assertSame(CaseModel::STATUS_CLOSED, $case->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $investigator->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $investigator->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_CASE_CLOSED,
        ]);
    }

    public function test_a_combined_field_edit_and_closure_writes_both_audits(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'case_title' => 'Original title',
            'status' => CaseModel::STATUS_FOR_REVIEW,
        ]);

        $this->actingAs($investigator)->put(route('cases.update', $case), $this->payload($case, [
            'case_title' => 'Corrected before closure',
            'status' => CaseModel::STATUS_CLOSED,
        ]))->assertRedirect(route('cases.show', $case));

        $case->refresh();
        $this->assertSame('Corrected before closure', $case->case_title);
        $this->assertSame(CaseModel::STATUS_CLOSED, $case->status);
        $this->assertSame(1, $case->auditLogs()->where('action_performed', AuditLog::ACTION_EDIT)->count());
        $this->assertSame(1, $case->auditLogs()->where('action_performed', AuditLog::ACTION_CASE_CLOSED)->count());
    }

    public function test_the_assigned_investigator_can_reopen_to_a_chosen_status_with_both_audits(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_CLOSED,
        ]);

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), $this->payload($case, [
                'status' => CaseModel::STATUS_UNDER_INVESTIGATION,
            ]))
            ->assertRedirect(route('cases.show', $case));

        $this->assertSame(CaseModel::STATUS_UNDER_INVESTIGATION, $case->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $investigator->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $investigator->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_CASE_REOPENED,
        ]);
    }

    public function test_a_closure_audit_failure_rolls_back_the_case_and_ordinary_edit_audit(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'case_title' => 'Original title',
            'status' => CaseModel::STATUS_FOR_REVIEW,
        ]);

        AuditLog::creating(function (AuditLog $audit): void {
            if ($audit->action_performed === AuditLog::ACTION_CASE_CLOSED) {
                throw new RuntimeException('Forced closure-audit failure.');
            }
        });

        try {
            $this->withoutExceptionHandling()
                ->actingAs($investigator)
                ->put(route('cases.update', $case), $this->payload($case, [
                    'case_title' => 'Must roll back',
                    'status' => CaseModel::STATUS_CLOSED,
                ]));
            $this->fail('The forced audit failure was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Forced closure-audit failure.', $exception->getMessage());
        } finally {
            AuditLog::flushEventListeners();
        }

        $case->refresh();
        $this->assertSame('Original title', $case->case_title);
        $this->assertSame(CaseModel::STATUS_FOR_REVIEW, $case->status);
        $this->assertDatabaseMissing('audit_logs', ['case_id' => $case->id]);
    }

    public function test_another_investigator_cannot_close_or_reopen_the_case(): void
    {
        $owner = User::factory()->investigator()->create();
        $other = User::factory()->investigator()->create();

        foreach ([CaseModel::STATUS_FOR_REVIEW => CaseModel::STATUS_CLOSED, CaseModel::STATUS_CLOSED => CaseModel::STATUS_DOCKETED] as $from => $to) {
            $case = CaseModel::factory()->assignedTo($owner)->create(['status' => $from]);

            $this->actingAs($other)
                ->put(route('cases.update', $case), $this->payload($case, ['status' => $to]))
                ->assertForbidden();

            $this->assertSame($from, $case->fresh()->status);
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $other->id,
                'case_id' => $case->id,
                'action_performed' => AuditLog::ACTION_ACCESS_DENIED,
            ]);
            $this->assertDatabaseMissing('audit_logs', [
                'case_id' => $case->id,
                'action_performed' => $to === CaseModel::STATUS_CLOSED
                    ? AuditLog::ACTION_CASE_CLOSED
                    : AuditLog::ACTION_CASE_REOPENED,
            ]);
        }
    }

    public function test_a_supervisor_cannot_close_or_reopen_through_a_forged_update(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create();

        foreach ([CaseModel::STATUS_FOR_REVIEW => CaseModel::STATUS_CLOSED, CaseModel::STATUS_CLOSED => CaseModel::STATUS_DOCKETED] as $from => $to) {
            $case = CaseModel::factory()->assignedTo($investigator)->create(['status' => $from]);

            $this->actingAs($supervisor)
                ->put(route('cases.update', $case), $this->payload($case, [
                    'status' => $to,
                    'reason' => 'Covering leave.',
                ]))
                ->assertForbidden();

            $this->assertSame($from, $case->fresh()->status);
            $this->assertDatabaseHas('audit_logs', [
                'user_id' => $supervisor->id,
                'case_id' => $case->id,
                'action_performed' => AuditLog::ACTION_ACCESS_DENIED,
            ]);
            $this->assertDatabaseMissing('audit_logs', [
                'case_id' => $case->id,
                'action_performed' => $to === CaseModel::STATUS_CLOSED
                    ? AuditLog::ACTION_CASE_CLOSED
                    : AuditLog::ACTION_CASE_REOPENED,
            ]);
        }
    }

    public function test_a_supervisor_can_edit_a_closed_case_without_changing_its_status(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create([
            'case_title' => 'Original title',
            'status' => CaseModel::STATUS_CLOSED,
        ]);

        $this->actingAs($supervisor)
            ->put(route('cases.update', $case), $this->payload($case, [
                'case_title' => 'Corrected title',
                'reason' => 'Correcting the closed record during turnover.',
            ]))
            ->assertRedirect(route('cases.show', $case));

        $case->refresh();
        $this->assertSame('Corrected title', $case->case_title);
        $this->assertSame(CaseModel::STATUS_CLOSED, $case->status);
        $this->assertDatabaseHas('audit_logs', [
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT_ON_BEHALF,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_CASE_REOPENED,
        ]);
    }

    public function test_the_status_selector_matches_closure_authority(): void
    {
        $investigator = User::factory()->investigator()->create();
        $supervisor = User::factory()->supervisor()->create();
        $open = CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_FOR_REVIEW]);
        $closed = CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_CLOSED]);

        $this->actingAs($investigator)->get(route('cases.edit', $open))
            ->assertOk()
            ->assertSee('<option value="Closed"', false);

        $this->actingAs($supervisor)->get(route('cases.edit', $open))
            ->assertOk()
            ->assertDontSee('<option value="Closed"', false);

        $closedForm = $this->actingAs($supervisor)->get(route('cases.edit', $closed))->assertOk();
        $closedForm->assertSee('<option value="Closed"', false)
            ->assertDontSee('<option value="Docketed"', false)
            ->assertDontSee('<option value="Under investigation"', false)
            ->assertDontSee('<option value="For review"', false);
    }

    public function test_an_admin_cannot_access_case_editing(): void
    {
        $admin = User::factory()->admin()->create();
        $case = CaseModel::factory()->create(['status' => CaseModel::STATUS_FOR_REVIEW]);

        $this->actingAs($admin)
            ->put(route('cases.update', $case), $this->payload($case, ['status' => CaseModel::STATUS_CLOSED]))
            ->assertForbidden();

        $this->assertSame(CaseModel::STATUS_FOR_REVIEW, $case->fresh()->status);
    }

    public function test_the_removed_closure_routes_are_not_found(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)->put("/cases/{$case->id}/closure")->assertNotFound();
        $this->actingAs($investigator)->get("/cases/{$case->id}/closure")->assertNotFound();
        $this->actingAs($investigator)->put("/cases/{$case->id}/closure/resolve")->assertNotFound();
    }

    public function test_a_stale_closure_attempt_is_rejected_without_audits(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_FOR_REVIEW]);
        $staleStamp = $case->updated_at->format('Y-m-d H:i:s');

        $this->travel(1)->seconds();
        $case->update(['case_title' => 'Updated elsewhere']);

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), $this->payload($case, [
                'case_title' => 'Stale closure',
                'status' => CaseModel::STATUS_CLOSED,
                'updated_at' => $staleStamp,
            ]))
            ->assertSessionHasErrors('updated_at');

        $this->assertSame(CaseModel::STATUS_FOR_REVIEW, $case->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['case_id' => $case->id]);
    }
}
