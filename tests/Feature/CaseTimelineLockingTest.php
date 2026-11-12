<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseTimelineLockingTest extends TestCase
{
    use RefreshDatabase;

    private function timeline(CaseModel $case, array $extra = []): array
    {
        return array_merge(['lock_version' => $case->lock_version, 'date_of_docket' => '2026-01-01', 'date_submission_rop' => '2026-01-10'], $extra);
    }

    private function edit(CaseModel $case): array
    {
        return $case->only(['case_title', 'incident_details', 'source_info', 'status', 'complexity_weight', 'lock_version']);
    }

    public function test_timeline_requires_version_and_rejects_two_open_forms(): void
    {
        $actor = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($actor)->create();
        $this->actingAs($actor)->get(route('cases.timeline.edit', $case))->assertOk()->assertSee('name="lock_version" value="0"', false)->assertSee(route('cases.edit', $case), false);
        $this->put(route('cases.timeline.update', $case), ['date_of_docket' => '2026-01-01'])->assertSessionHasErrors('lock_version');
        $this->put(route('cases.timeline.update', $case), $this->timeline($case))->assertSessionHasNoErrors();
        $this->assertSame(1, $case->fresh()->lock_version);
        $this->from(route('cases.timeline.edit', $case))->put(route('cases.timeline.update', $case), $this->timeline($case, ['date_submission_rop' => '2026-01-11']))->assertSessionHasErrors('lock_version');
        $this->get(route('cases.timeline.edit', $case))->assertSee('value="2026-01-11"', false)->assertSee('name="lock_version" value="0"', false);
        $this->assertSame('2026-01-10', $case->fresh()->timeline->date_submission_rop->format('Y-m-d'));
        $this->assertSame(1, AuditLog::where('case_id', $case->id)->where('action_performed', AuditLog::ACTION_TIMELINE_UPDATE)->count());
    }

    public function test_case_and_timeline_editors_invalidate_each_other(): void
    {
        $actor = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($actor)->create(['status' => CaseModel::STATUS_DOCKETED]);
        $this->actingAs($actor)->put(route('cases.update', $case), $this->edit($case))->assertSessionHasNoErrors();
        $this->put(route('cases.timeline.update', $case), $this->timeline($case))->assertSessionHasErrors('lock_version');
        $case->refresh();
        $this->put(route('cases.timeline.update', $case), $this->timeline($case))->assertSessionHasNoErrors();
        $this->put(route('cases.update', $case), $this->edit($case))->assertSessionHasErrors('lock_version');
        $this->assertSame(2, $case->fresh()->lock_version);
    }

    public function test_supervisor_reason_is_required_bounded_and_transactionally_audited(): void
    {
        $case = CaseModel::factory()->create();
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor)->put(route('cases.timeline.update', $case), $this->timeline($case))->assertSessionHasErrors('reason');
        $this->put(route('cases.timeline.update', $case), $this->timeline($case, ['reason' => str_repeat('x', 501)]))->assertSessionHasErrors('reason');
        $this->put(route('cases.timeline.update', $case), $this->timeline($case, ['reason' => 'Coverage correction']))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['case_id' => $case->id, 'user_id' => $supervisor->id, 'action_performed' => AuditLog::ACTION_TIMELINE_EDIT_ON_BEHALF, 'notes' => 'Coverage correction']);
        $this->assertDatabaseHas('audit_logs', ['case_id' => $case->id, 'action_performed' => AuditLog::ACTION_TIMELINE_UPDATE]);
    }

    public function test_reassignment_invalidates_a_supervisors_open_timeline_and_revokes_old_investigator_access(): void
    {
        $actor = User::factory()->investigator()->create();
        $next = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($actor)->create();
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor)->put(route('cases.reassign.update', $case), ['investigator_id' => $next->id, 'docket_no' => $case->docket_no])->assertSessionHasNoErrors();
        $this->put(route('cases.timeline.update', $case), $this->timeline($case, ['reason' => 'Stale form']))->assertSessionHasErrors('lock_version');
        $this->actingAs($actor)->put(route('cases.timeline.update', $case), $this->timeline($case))->assertForbidden();
        $this->assertNull($case->fresh()->timeline);
    }

    public function test_audit_failure_rolls_back_timeline_version_and_both_audits(): void
    {
        $case = CaseModel::factory()->create();
        $this->actingAs(User::factory()->supervisor()->create());
        AuditLog::creating(function ($audit) {
            if ($audit->action_performed === AuditLog::ACTION_TIMELINE_EDIT_ON_BEHALF) {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        $this->put(route('cases.timeline.update', $case), $this->timeline($case, ['reason' => 'Synthetic reason']))->assertStatus(500);
        $this->assertNull($case->fresh()->timeline);
        $this->assertSame(0, $case->fresh()->lock_version);
        $this->assertDatabaseMissing('audit_logs', ['case_id' => $case->id, 'action_performed' => AuditLog::ACTION_TIMELINE_UPDATE]);
    }
}
