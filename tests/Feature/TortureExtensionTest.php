<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TortureExtensionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(?bool $classification = true): array
    {
        $actor = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($actor)->create(['status' => CaseModel::STATUS_DOCKETED, 'is_torture_case' => $classification]);
        $case->timeline()->create(['date_of_docket' => '2026-01-01', 'date_submission_rop' => '2026-01-10']);

        return [$actor, $case];
    }

    private function payload(CaseModel $case, array $extra = []): array
    {
        return array_merge(['lock_version' => $case->fresh()->lock_version, 'date_of_docket' => '2026-01-01', 'date_submission_rop' => '2026-01-10'], $extra);
    }

    public function test_external_grant_and_removal_require_notes_and_are_audited(): void
    {
        [$actor, $case] = $this->fixture();
        $this->actingAs($actor)->put(route('cases.timeline.update', $case), $this->payload($case, ['torture_extension_granted' => 1]))->assertSessionHasErrors('extension_note');
        $this->assertSame(0, $case->fresh()->lock_version);
        $this->put(route('cases.timeline.update', $case), $this->payload($case, ['torture_extension_granted' => 1, 'extension_note' => 'External decision reference SYNTHETIC-1']))->assertSessionHasNoErrors();
        $this->assertSame('2026-04-01', $case->fresh()->timeline->extension_60th_days->format('Y-m-d'));
        $this->assertSame(1, $case->fresh()->lock_version);
        $audit = AuditLog::where('case_id', $case->id)->where('action_performed', AuditLog::ACTION_TORTURE_EXTENSION_RECORDED)->sole();
        $this->assertSame('2026-04-01', json_decode($audit->notes, true)['deadline']);
        $this->assertSame('External decision reference SYNTHETIC-1', json_decode($audit->notes, true)['note']);
        $this->put(route('cases.timeline.update', $case), $this->payload($case, ['torture_extension_granted' => 0]))->assertSessionHasErrors('extension_note');
        $this->put(route('cases.timeline.update', $case), $this->payload($case, ['torture_extension_granted' => 0, 'extension_note' => 'Correcting mistaken grant']))->assertSessionHasNoErrors();
        $this->assertNull($case->fresh()->timeline->extension_60th_days);
        $this->assertDatabaseHas('audit_logs', ['case_id' => $case->id, 'action_performed' => AuditLog::ACTION_TORTURE_EXTENSION_REMOVED]);
    }

    public function test_omitted_control_preserves_grant_and_docket_change_recalculates_with_note(): void
    {
        [$actor, $case] = $this->fixture();
        $case->timeline->update(['extension_60th_days' => '2026-04-01']);
        $this->actingAs($actor)->put(route('cases.timeline.update', $case), $this->payload($case))->assertSessionHasNoErrors();
        $this->assertSame('2026-04-01', $case->fresh()->timeline->extension_60th_days->format('Y-m-d'));
        $this->put(route('cases.timeline.update', $case), $this->payload($case, ['date_of_docket' => '2026-01-02']))->assertSessionHasErrors('extension_note');
        $this->put(route('cases.timeline.update', $case), $this->payload($case, ['date_of_docket' => '2026-01-02', 'extension_note' => 'Docket date correction']))->assertSessionHasNoErrors();
        $this->assertSame('2026-04-02', $case->fresh()->timeline->extension_60th_days->format('Y-m-d'));
        $audit = AuditLog::where('case_id', $case->id)->where('action_performed', AuditLog::ACTION_TORTURE_EXTENSION_RECALCULATED)->sole();
        $this->assertSame('2026-04-01', json_decode($audit->notes, true)['previous_deadline']);
        $this->assertSame('2026-04-02', json_decode($audit->notes, true)['deadline']);
    }

    public function test_requests_raw_dates_and_ineligible_classifications_cannot_grant_extensions(): void
    {
        [$actor, $case] = $this->fixture();
        $this->actingAs($actor)->put(route('cases.timeline.update', $case), $this->payload($case, ['date_extension_30_days_requested' => '2026-02-01']))->assertSessionHasNoErrors();
        $this->assertNull($case->fresh()->timeline->extension_60th_days);
        $this->put(route('cases.timeline.update', $case), $this->payload($case, ['extension_60th_days' => '2026-06-01']))->assertSessionHasErrors('extension_60th_days');
        $this->put(route('cases.timeline.update', $case), $this->payload($case, ['torture_extension_granted' => 1, 'extension_note' => str_repeat('x', 501)]))->assertSessionHasErrors('extension_note');
        foreach ([false, null] as $classification) {
            [$other, $ineligible] = $this->fixture($classification);
            $this->actingAs($other)->put(route('cases.timeline.update', $ineligible), $this->payload($ineligible, ['torture_extension_granted' => 1, 'extension_note' => 'Not eligible']))->assertSessionHasErrors('torture_extension_granted');
            $this->assertNull($ineligible->fresh()->timeline->extension_60th_days);
        }
    }

    public function test_classification_change_requires_clearing_the_extension_first(): void
    {
        [$actor, $case] = $this->fixture();
        $case->timeline->update(['extension_60th_days' => '2026-04-01']);
        $fields = $case->only(['case_title', 'incident_details', 'source_info', 'status', 'complexity_weight', 'lock_version']);
        foreach ([0, null] as $classification) {
            $this->actingAs($actor)->put(route('cases.update', $case), $fields + ['is_torture_case' => $classification])->assertSessionHasErrors('is_torture_case');
        }
        $this->put(route('cases.timeline.update', $case), $this->payload($case, ['torture_extension_granted' => 0, 'extension_note' => 'Mistaken classification and grant']))->assertSessionHasNoErrors();
        $this->put(route('cases.update', $case), array_merge($fields, ['lock_version' => 1, 'is_torture_case' => 0]))->assertSessionHasNoErrors();
        $this->assertFalse($case->fresh()->is_torture_case);
    }

    public function test_permissions_supervisor_reason_and_stale_grant_submission(): void
    {
        [$actor, $case] = $this->fixture();
        $grant = $this->payload($case, ['torture_extension_granted' => 1, 'extension_note' => 'External reference']);
        $this->actingAs(User::factory()->investigator()->create())->put(route('cases.timeline.update', $case), $grant)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->put(route('cases.timeline.update', $case), $grant)->assertForbidden();
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor)->put(route('cases.timeline.update', $case), $grant)->assertSessionHasErrors('reason');
        $this->put(route('cases.timeline.update', $case), $grant + ['reason' => 'Recording external decision during coverage'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['case_id' => $case->id, 'action_performed' => AuditLog::ACTION_TIMELINE_EDIT_ON_BEHALF]);
        $this->actingAs($actor)->put(route('cases.timeline.update', $case), $grant)->assertSessionHasErrors('lock_version');
        $this->assertSame(1, $case->fresh()->lock_version);
    }

    public function test_audit_failure_rolls_back_extension_timeline_and_parent_version(): void
    {
        [$actor, $case] = $this->fixture();
        $this->actingAs($actor);
        AuditLog::creating(function ($audit) {
            if ($audit->action_performed === AuditLog::ACTION_TORTURE_EXTENSION_RECORDED) {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        $this->put(route('cases.timeline.update', $case), $this->payload($case, ['date_of_docket' => '2026-01-02', 'torture_extension_granted' => 1, 'extension_note' => 'Synthetic reference']))->assertStatus(500);
        $this->assertSame(0, $case->fresh()->lock_version);
        $this->assertSame('2026-01-01', $case->fresh()->timeline->date_of_docket->format('Y-m-d'));
        $this->assertNull($case->fresh()->timeline->extension_60th_days);
        $this->assertDatabaseMissing('audit_logs', ['case_id' => $case->id, 'action_performed' => AuditLog::ACTION_TIMELINE_UPDATE]);
    }

    public function test_dashboard_alerts_details_and_report_use_the_same_effective_deadline(): void
    {
        $this->travelTo(now()->setDate(2026, 3, 25));
        [$actor, $case] = $this->fixture();
        $case->timeline->update(['extension_60th_days' => '2026-04-01']);
        $this->actingAs($actor)->get(route('home'))->assertOk()->assertSee('01 Apr 2026')->assertSee('RORP deadline');
        $this->get(route('alerts.index'))->assertOk()->assertSee('01 Apr 2026')->assertSee('RORP deadline');
        $this->get(route('cases.show', $case))->assertOk()->assertSee('01 Apr 2026')->assertSee('External 30-day extension recorded.');
        $this->get(route('reports.show', $case))->assertOk()->assertSee('01 Apr 2026')->assertSee('External 30-day extension recorded.');
        $this->get(route('cases.timeline.edit', $case))->assertOk()->assertSee('01 Apr 2026');
    }

    public function test_unknown_classification_displays_an_explicit_evaluation_caveat(): void
    {
        [$actor, $case] = $this->fixture(null);
        $this->actingAs($actor)->get(route('cases.show', $case))->assertOk()->assertSee('60-day deadline not evaluated');
        $this->get(route('reports.show', $case))->assertOk()->assertSee('60-day deadline not evaluated');
        $this->get(route('home'))->assertOk()->assertSee('unknown classification');
        $this->get(route('alerts.index'))->assertOk()->assertSee('unknown classification');
    }

    public function test_preflight_reports_conflicts_without_changing_records(): void
    {
        [$actor, $case] = $this->fixture();
        $case->timeline->update(['submission_60th_day' => '2026-03-03', 'extension_60th_days' => '2026-04-02']);
        [, $other] = $this->fixture(false);
        $other->timeline->update(['extension_60th_days' => '2026-04-01']);
        $before = CaseTimeline::orderBy('id')->get()->toArray();
        $this->artisan('cases:check-torture-deadlines')->expectsOutput('Base deadline conflicts: 1')
            ->expectsOutput('Extended deadline conflicts: 1')->expectsOutput('Extension classification conflicts: 1')->assertExitCode(1);
        $this->assertSame($before, CaseTimeline::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
