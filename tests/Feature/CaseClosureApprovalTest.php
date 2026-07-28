<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Maker-checker on case closure: the assigned investigator proposes, and only
 * a supervisor may confirm or reject.
 *
 * The check that carries the feature is that a Maker cannot approve their own
 * proposal, so the denied cases here hit the raw URL rather than a route()
 * helper, and every one of them asserts the case did not move.
 */
class CaseClosureApprovalTest extends TestCase
{
    use RefreshDatabase;

    // --------------------------------------------------------------- propose

    public function test_an_investigator_can_propose_closure_on_their_own_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => 'Under investigation',
        ]);

        $this->actingAs($investigator)
            ->put(route('cases.closure.propose', $case))
            ->assertRedirect(route('cases.show', $case));

        $case->refresh();

        // Proposing parks the case; it does not close it.
        $this->assertSame(CaseModel::STATUS_PENDING_CLOSURE, $case->status);
        $this->assertSame('Under investigation', $case->status_before_closure);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $investigator->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_CLOSURE_PROPOSED,
        ]);
    }

    public function test_an_investigator_cannot_propose_closure_on_another_investigators_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();
        $theirCase = CaseModel::factory()->assignedTo($colleague)->create([
            'status' => 'Under investigation',
        ]);

        $this->actingAs($investigator)
            ->put("/cases/{$theirCase->id}/closure")
            ->assertForbidden();

        $this->assertSame('Under investigation', $theirCase->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_proposing_twice_does_not_overwrite_the_stored_prior_status(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => 'Under investigation',
        ]);

        $this->actingAs($investigator)->put(route('cases.closure.propose', $case));
        $this->actingAs($investigator)->put(route('cases.closure.propose', $case));

        $case->refresh();

        // The second proposal must not copy "Pending Closure" over the value a
        // rejection has to restore.
        $this->assertSame(CaseModel::STATUS_PENDING_CLOSURE, $case->status);
        $this->assertSame('Under investigation', $case->status_before_closure);

        // And it is not a second event worth recording, because nothing moved.
        $this->assertDatabaseCount('audit_logs', 1);
    }

    // --------------------------------------------------------------- resolve

    public function test_an_investigator_cannot_confirm_their_own_proposed_closure(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        $this->actingAs($investigator)
            ->put("/cases/{$case->id}/closure/resolve", ['decision' => 'confirm'])
            ->assertForbidden();

        $this->assertSame(CaseModel::STATUS_PENDING_CLOSURE, $case->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_investigator_cannot_reach_the_closure_review_page(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        $this->actingAs($investigator)
            ->get("/cases/{$case->id}/closure")
            ->assertForbidden();
    }

    public function test_a_supervisor_is_shown_the_proposal_before_deciding(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        $this->actingAs($supervisor)
            ->get(route('cases.closure.review', $case))
            ->assertOk()
            ->assertSee($case->docket_no)
            ->assertSee('Confirm Closure')
            ->assertSee('Reject Closure')
            // Where rejecting sends the case, rather than leaving it to guesswork.
            ->assertSee('Under investigation');

        // Reaching the page must not decide anything by itself.
        $this->assertSame(CaseModel::STATUS_PENDING_CLOSURE, $case->fresh()->status);
    }

    public function test_a_supervisor_can_confirm_a_proposed_closure_and_it_is_audited(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        $this->actingAs($supervisor)
            ->put(route('cases.closure.resolve', $case), ['decision' => 'confirm'])
            ->assertRedirect(route('cases.show', $case));

        $case->refresh();

        $this->assertSame(CaseModel::STATUS_CLOSED, $case->status);
        $this->assertNull($case->status_before_closure);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_CLOSURE_CONFIRMED,
        ]);
    }

    public function test_a_rejected_closure_returns_the_case_to_its_exact_prior_status(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        $this->actingAs($supervisor)
            ->put(route('cases.closure.resolve', $case), ['decision' => 'reject'])
            ->assertRedirect(route('cases.show', $case));

        $case->refresh();

        // Not Docketed: a case under investigation must not look like it
        // regressed to intake because someone asked to close it.
        $this->assertSame('Under investigation', $case->status);
        $this->assertNull($case->status_before_closure);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_CLOSURE_REJECTED,
        ]);
    }

    public function test_a_closure_that_was_never_proposed_cannot_be_resolved(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create(['status' => 'Under investigation']);

        $this->actingAs($supervisor)
            ->get("/cases/{$case->id}/closure")
            ->assertNotFound();

        $this->actingAs($supervisor)
            ->put("/cases/{$case->id}/closure/resolve", ['decision' => 'confirm'])
            ->assertNotFound();

        $this->assertSame('Under investigation', $case->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_decision_must_be_one_of_confirm_or_reject(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        $this->actingAs($supervisor)
            ->put(route('cases.closure.resolve', $case), ['decision' => 'maybe'])
            ->assertSessionHasErrors('decision');

        $this->assertSame(CaseModel::STATUS_PENDING_CLOSURE, $case->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    // ----------------------------------------------- the edit-form back door

    public function test_the_ordinary_edit_form_cannot_close_a_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => 'Under investigation',
        ]);

        // Typing "Closed" into the status box is the whole reason
        // CaseModel::statusRulesFor() exists.
        $this->actingAs($investigator)
            ->put(route('cases.update', $case), [
                'case_title' => $case->case_title,
                'incident_details' => $case->incident_details,
                'status' => CaseModel::STATUS_CLOSED,
                'complexity_weight' => $case->complexity_weight,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('Under investigation', $case->fresh()->status);
    }

    public function test_the_ordinary_edit_form_cannot_withdraw_a_pending_closure(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        // Otherwise the edit form is an unaudited way to cancel a proposal
        // before the supervisor ever sees it.
        $this->actingAs($investigator)
            ->put(route('cases.update', $case), [
                'case_title' => $case->case_title,
                'incident_details' => $case->incident_details,
                'status' => CaseModel::STATUS_DOCKETED,
                'complexity_weight' => $case->complexity_weight,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(CaseModel::STATUS_PENDING_CLOSURE, $case->fresh()->status);
    }

    public function test_an_already_closed_case_can_still_be_edited(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create(['status' => CaseModel::STATUS_CLOSED]);

        // Blocking the closure states outright would make a closed case
        // permanently uneditable, including to fix a typo in its title.
        $this->actingAs($supervisor)
            ->put(route('cases.update', $case), [
                'case_title' => 'Corrected title',
                'incident_details' => $case->incident_details,
                'status' => CaseModel::STATUS_CLOSED,
                'complexity_weight' => $case->complexity_weight,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Corrected title', $case->fresh()->case_title);
    }

    // ------------------------------------------------------------------ misc

    public function test_a_pending_closure_still_counts_toward_the_investigators_workload(): void
    {
        $investigator = User::factory()->investigator()->create(['performance_rating' => 1.0]);

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'complexity_weight' => 4,
        ]);

        // A case is not closed until the supervisor says so, so it stays in
        // A_i while it waits. scopeActive() excludes only STATUS_CLOSED.
        $this->assertSame(4.0, $investigator->workloadCapacityScore());

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_CLOSED,
            'complexity_weight' => 5,
        ]);

        $this->assertSame(4.0, $investigator->fresh()->workloadCapacityScore());
    }

    public function test_the_review_control_is_hidden_from_investigators(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        $this->actingAs($investigator)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertDontSee('Review Closure')
            // Already proposed, so the Maker's control is gone too.
            ->assertDontSee('Propose Closure')
            ->assertDontSee(route('cases.closure.review', $case));
    }

    public function test_a_user_without_a_case_handling_role_is_blocked_by_middleware(): void
    {
        $outsider = User::factory()->withRole('Records Clerk')->create();
        $case = CaseModel::factory()->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        $this->actingAs($outsider)->put("/cases/{$case->id}/closure")->assertForbidden();
        $this->actingAs($outsider)->get("/cases/{$case->id}/closure")->assertForbidden();
        $this->actingAs($outsider)
            ->put("/cases/{$case->id}/closure/resolve", ['decision' => 'confirm'])
            ->assertForbidden();

        $this->assertSame(CaseModel::STATUS_PENDING_CLOSURE, $case->fresh()->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $case = CaseModel::factory()->create([
            'status' => CaseModel::STATUS_PENDING_CLOSURE,
            'status_before_closure' => 'Under investigation',
        ]);

        $this->put(route('cases.closure.propose', $case))->assertRedirect('/login');
        $this->get(route('cases.closure.review', $case))->assertRedirect('/login');
        $this->put(route('cases.closure.resolve', $case), ['decision' => 'confirm'])
            ->assertRedirect('/login');

        $this->assertSame(CaseModel::STATUS_PENDING_CLOSURE, $case->fresh()->status);
    }
}
