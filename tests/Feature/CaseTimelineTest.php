<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseTimelineTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function timelinePayload(array $overrides = []): array
    {
        return array_merge([
            'date_of_docket' => '2026-06-12',
            'date_submission_rop' => '2026-06-19',
            'extension_30_days' => '2026-07-12',
            'submission_60th_day' => '2026-08-11',
            'submission_120th_day' => '2026-10-10',
            'target_date_fir' => '2026-09-01',
            'date_fir_submitted' => '2026-08-28',
            'date_submitted_to' => 'Regional Director',
        ], $overrides);
    }

    public function test_the_set_timeline_form_renders_for_the_assigned_investigator(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->timeline()->create(['date_of_docket' => '2026-06-12']);

        $this->actingAs($investigator)
            ->get(route('cases.timeline.edit', $case))
            ->assertOk()
            ->assertSee('Set Timeline')
            ->assertSee($case->docket_no)
            ->assertSee('Submission (60th Day)')
            // Existing values are prefilled.
            ->assertSee('2026-06-12', escape: false);
    }

    public function test_the_assigned_investigator_can_set_every_milestone(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->timeline()->create(['date_of_docket' => '2026-06-12']);

        $this->actingAs($investigator)
            ->put(route('cases.timeline.update', $case), $this->timelinePayload())
            ->assertRedirect(route('cases.show', $case))
            ->assertSessionHasNoErrors();

        $timeline = $case->fresh()->timeline;

        $this->assertSame('2026-06-19', $timeline->date_submission_rop->toDateString());
        $this->assertSame('2026-07-12', $timeline->extension_30_days->toDateString());
        $this->assertSame('2026-08-11', $timeline->submission_60th_day->toDateString());
        $this->assertSame('2026-10-10', $timeline->submission_120th_day->toDateString());
        $this->assertSame('2026-09-01', $timeline->target_date_fir->toDateString());
        $this->assertSame('2026-08-28', $timeline->date_fir_submitted->toDateString());
        $this->assertSame('Regional Director', $timeline->date_submitted_to);
    }

    public function test_the_saved_dates_render_on_the_case_profile_matrix(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)
            ->put(route('cases.timeline.update', $case), $this->timelinePayload());

        $this->actingAs($investigator)
            ->get(route('cases.show', $case))
            ->assertOk()
            ->assertSee('12 Jun 2026')
            ->assertSee('19 Jun 2026')
            ->assertSee('11 Aug 2026')
            ->assertSee('10 Oct 2026')
            ->assertSee('28 Aug 2026')
            ->assertSee('Regional Director')
            ->assertDontSee('No timeline has been recorded for this case yet.');
    }

    public function test_a_case_docketed_before_timelines_existed_can_still_be_given_one(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->assertNull($case->timeline);

        $this->actingAs($investigator)
            ->put(route('cases.timeline.update', $case), $this->timelinePayload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('case_timelines', 1);
        $this->assertSame('2026-06-12', $case->fresh()->timeline->date_of_docket->toDateString());
    }

    public function test_the_milestones_are_optional_and_can_be_cleared(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->timeline()->create($this->timelinePayload());

        $this->actingAs($investigator)
            ->put(route('cases.timeline.update', $case), [
                'date_of_docket' => '2026-06-12',
                'date_submission_rop' => '',
                'extension_30_days' => '',
                'submission_60th_day' => '',
                'submission_120th_day' => '',
                'target_date_fir' => '',
                'date_fir_submitted' => '',
                'date_submitted_to' => '',
            ])
            ->assertSessionHasNoErrors();

        $timeline = $case->fresh()->timeline;

        $this->assertNull($timeline->submission_60th_day);
        $this->assertNull($timeline->date_submitted_to);
        $this->assertSame('2026-06-12', $timeline->date_of_docket->toDateString());
    }

    public function test_the_date_of_docket_cannot_be_cleared(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->timeline()->create(['date_of_docket' => '2026-06-12']);

        $this->actingAs($investigator)
            ->put(route('cases.timeline.update', $case), $this->timelinePayload(['date_of_docket' => '']))
            ->assertSessionHasErrors('date_of_docket');

        $this->assertSame('2026-06-12', $case->fresh()->timeline->date_of_docket->toDateString());
    }

    /**
     * New with case import (wiki/project/case-import.md): every milestone
     * must fall on or after date_of_docket. Shared via
     * CaseTimeline::chronologyRules() rather than import-only, so the manual
     * form gains the same guard — nothing enforced this before.
     */
    public function test_a_milestone_before_the_date_of_docket_is_rejected(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->timeline()->create(['date_of_docket' => '2026-06-12']);

        $this->actingAs($investigator)
            ->put(route('cases.timeline.update', $case), $this->timelinePayload([
                'submission_120th_day' => '2026-01-01',
            ]))
            ->assertSessionHasErrors('submission_120th_day');

        $this->assertNull($case->fresh()->timeline->submission_120th_day);
    }

    public function test_a_supervisor_can_set_the_timeline_on_any_case(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $case = CaseModel::factory()->create();

        $this->actingAs($supervisor)
            ->get(route('cases.timeline.edit', $case))
            ->assertOk();

        $this->actingAs($supervisor)
            ->put(route('cases.timeline.update', $case), $this->timelinePayload())
            ->assertRedirect(route('cases.show', $case));

        $this->assertSame('Regional Director', $case->fresh()->timeline->date_submitted_to);
    }

    public function test_another_investigator_cannot_touch_the_timeline_by_direct_url(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($colleague)->create();
        $case->timeline()->create(['date_of_docket' => '2026-06-12']);

        $this->actingAs($investigator)
            ->get("/cases/{$case->id}/timeline/edit")
            ->assertForbidden();

        $this->actingAs($investigator)
            ->put("/cases/{$case->id}/timeline", $this->timelinePayload())
            ->assertForbidden();

        $this->assertNull($case->fresh()->timeline->date_submitted_to);
        $this->assertDatabaseMissing('audit_logs', ['action_performed' => AuditLog::ACTION_TIMELINE_UPDATE]);
        $this->assertSame(2, AuditLog::where('user_id', $investigator->id)
            ->where('case_id', $case->id)
            ->where('action_performed', AuditLog::ACTION_ACCESS_DENIED)
            ->count());
    }

    // ----------------------------------------------------------------- audit

    public function test_setting_the_timeline_is_audited(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->timeline()->create(['date_of_docket' => '2026-06-12']);

        $this->actingAs($investigator)
            ->put(route('cases.timeline.update', $case), $this->timelinePayload())
            ->assertSessionHasNoErrors();

        // Against the parent case: the milestones are what the 30/60/120-day
        // alerts read, so who moved them is a question about the case.
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $investigator->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_TIMELINE_UPDATE,
        ]);
    }

    public function test_a_rejected_timeline_edit_writes_no_audit_entry(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->timeline()->create(['date_of_docket' => '2026-06-12']);

        $this->actingAs($investigator)
            ->put(route('cases.timeline.update', $case), $this->timelinePayload([
                'date_of_docket' => '',
            ]))
            ->assertSessionHasErrors('date_of_docket');

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_guests_are_redirected_to_the_login_screen(): void
    {
        $case = CaseModel::factory()->create();

        $this->get(route('cases.timeline.edit', $case))->assertRedirect('/login');
        $this->put(route('cases.timeline.update', $case), $this->timelinePayload())->assertRedirect('/login');
    }
}
