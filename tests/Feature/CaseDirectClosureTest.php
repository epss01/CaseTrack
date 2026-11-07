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

    public function test_ordinary_edit_cannot_cross_the_closed_boundary_even_for_the_owner(): void
    {
        $owner = User::factory()->investigator()->create();
        foreach ([CaseModel::STATUS_DOCKETED => CaseModel::STATUS_CLOSED, CaseModel::STATUS_CLOSED => CaseModel::STATUS_FOR_REVIEW] as $from => $to) {
            $case = CaseModel::factory()->assignedTo($owner)->create(['status' => $from]);
            $this->actingAs($owner)->put(route('cases.update', $case), $this->payload($case, ['status' => $to]))
                ->assertSessionHasErrors('status');
            $this->assertSame($from, $case->fresh()->status);
            $this->assertSame(0, $case->fresh()->lock_version);
            $this->assertDatabaseMissing('audit_logs', ['case_id' => $case->id]);
        }
    }

    public function test_reopening_accepts_each_active_status_and_restores_workload(): void
    {
        $owner = User::factory()->investigator()->create(['performance_rating' => 1.0]);
        foreach ([CaseModel::STATUS_DOCKETED, CaseModel::STATUS_UNDER_INVESTIGATION, CaseModel::STATUS_FOR_REVIEW] as $status) {
            $case = CaseModel::factory()->assignedTo($owner)->create(['status' => CaseModel::STATUS_CLOSED, 'complexity_weight' => 2]);
            $before = $owner->fresh()->workloadCapacityScore();
            $this->actingAs($owner)->put(route('cases.closure.update', $case), [
                'status' => $status, 'lock_version' => $case->lock_version,
            ])->assertRedirect(route('cases.show', $case));
            $this->assertSame($status, $case->fresh()->status);
            $this->assertSame(1, $case->fresh()->lock_version);
            $this->assertEquals($before + 2, $owner->fresh()->workloadCapacityScore());
        }
    }

    public function test_invalid_reopening_and_missing_version_do_not_change_the_case(): void
    {
        $owner = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($owner)->create(['status' => CaseModel::STATUS_CLOSED]);
        foreach (['', 'Pending Closure', CaseModel::STATUS_CLOSED] as $status) {
            $this->actingAs($owner)->put(route('cases.closure.update', $case), [
                'status' => $status, 'lock_version' => 0,
            ])->assertSessionHasErrors('status');
        }
        $this->put(route('cases.closure.update', $case), ['status' => CaseModel::STATUS_DOCKETED])
            ->assertSessionHasErrors('lock_version');
        $this->assertSame(CaseModel::STATUS_CLOSED, $case->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['case_id' => $case->id]);
    }

    public function test_repeated_closure_is_rejected_and_fir_dates_are_untouched(): void
    {
        $owner = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($owner)->create(['status' => CaseModel::STATUS_DOCKETED]);
        $case->timeline()->create(['date_of_docket' => '2026-01-01']);
        // Native HTML forms submit the hidden version as a string.
        $payload = ['status' => CaseModel::STATUS_CLOSED, 'lock_version' => '0'];
        $this->actingAs($owner)->put(route('cases.closure.update', $case), $payload)->assertSessionHasNoErrors();
        $this->put(route('cases.closure.update', $case), $payload)->assertSessionHasErrors('lock_version');
        $this->assertNull($case->fresh()->timeline->date_fir_submitted);
        $this->assertSame(1, $case->auditLogs()->where('action_performed', AuditLog::ACTION_CASE_CLOSED)->count());
        $this->assertEquals(0, $owner->workloadCapacityScore());
    }

    public function test_reassignment_invalidates_the_previous_owners_closure_form(): void
    {
        $owner = User::factory()->investigator()->create();
        $replacement = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($owner)->create(['status' => CaseModel::STATUS_DOCKETED]);
        $this->actingAs(User::factory()->supervisor()->create())->put(route('cases.reassign.update', $case), [
            'docket_no' => $case->docket_no, 'investigator_id' => $replacement->id,
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->put(route('cases.closure.update', $case), [
            'status' => CaseModel::STATUS_CLOSED, 'lock_version' => 0,
        ])->assertForbidden();
        $this->assertSame(CaseModel::STATUS_DOCKETED, $case->fresh()->status);
    }

    public function test_case_details_offer_only_the_authorized_closure_action(): void
    {
        $owner = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($owner)->create(['status' => CaseModel::STATUS_DOCKETED]);
        $this->actingAs($owner)->get(route('cases.show', $case))
            ->assertSee('data-bs-target="#caseClosure"', false)
            ->assertSee('Close case')->assertDontSee('id="reopen-status"', false);
        $case->update(['status' => CaseModel::STATUS_CLOSED]);
        $this->get(route('cases.show', $case))->assertSee('Reopen case')
            ->assertSee('id="reopen-status"', false)->assertSee('Choose a status');
        $this->actingAs(User::factory()->supervisor()->create())->get(route('cases.show', $case))
            ->assertDontSee('data-bs-target="#caseClosure"', false)->assertDontSee('id="caseClosure"', false);
    }

    /** @return array<string, mixed> */
    private function payload(CaseModel $case, array $overrides = []): array
    {
        return array_merge([
            'case_title' => $case->case_title,
            'incident_details' => $case->incident_details,
            'source_info' => $case->source_info,
            'status' => $case->status,
            'complexity_weight' => $case->complexity_weight,
            'lock_version' => $case->lock_version,
        ], $overrides);
    }

    public function test_the_assigned_investigator_can_close_their_case_with_both_audits(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_FOR_REVIEW,
        ]);

        $this->actingAs($investigator)
            ->put(route('cases.closure.update', $case), $this->payload($case, [
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

    public function test_closure_does_not_overwrite_other_case_fields(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'case_title' => 'Original title',
            'status' => CaseModel::STATUS_FOR_REVIEW,
        ]);

        $this->actingAs($investigator)->put(route('cases.closure.update', $case), $this->payload($case, [
            'case_title' => 'Corrected before closure',
            'status' => CaseModel::STATUS_CLOSED,
        ]))->assertRedirect(route('cases.show', $case));

        $case->refresh();
        $this->assertSame('Original title', $case->case_title);
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
            ->put(route('cases.closure.update', $case), $this->payload($case, [
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
                ->put(route('cases.closure.update', $case), $this->payload($case, [
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
                ->put(route('cases.closure.update', $case), $this->payload($case, ['status' => $to]))
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
                ->put(route('cases.closure.update', $case), $this->payload($case, [
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
            ->assertDontSee('<option value="Closed"', false);

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
            ->put(route('cases.closure.update', $case), $this->payload($case, ['status' => CaseModel::STATUS_CLOSED]))
            ->assertForbidden();

        $this->assertSame(CaseModel::STATUS_FOR_REVIEW, $case->fresh()->status);
    }

    public function test_the_old_supervisor_closure_resolution_route_is_not_found(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)->put("/cases/{$case->id}/closure/resolve")->assertNotFound();
    }

    public function test_a_stale_closure_attempt_is_rejected_without_audits(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_FOR_REVIEW]);
        $staleVersion = $case->lock_version;

        $case->increment('lock_version');

        $this->actingAs($investigator)
            ->put(route('cases.closure.update', $case), $this->payload($case, [
                'case_title' => 'Stale closure',
                'status' => CaseModel::STATUS_CLOSED,
                'lock_version' => $staleVersion,
            ]))
            ->assertSessionHasErrors('lock_version');

        $this->assertSame(CaseModel::STATUS_FOR_REVIEW, $case->fresh()->status);
        $this->assertDatabaseMissing('audit_logs', ['case_id' => $case->id]);
    }
}
