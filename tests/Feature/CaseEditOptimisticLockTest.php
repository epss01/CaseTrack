<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CaseEditOptimisticLockTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payload(CaseModel $case, int $lockVersion, array $overrides = []): array
    {
        return array_merge([
            'case_title' => $case->case_title,
            'incident_details' => $case->incident_details,
            'source_info' => $case->source_info,
            'status' => $case->status,
            'complexity_weight' => $case->complexity_weight,
            'lock_version' => $lockVersion,
        ], $overrides);
    }

    public function test_the_edit_form_posts_the_case_lock_version(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)
            ->get(route('cases.edit', $case))
            ->assertOk()
            ->assertSee('name="lock_version" value="0"', false)
            ->assertDontSee('name="updated_at"', false);
    }

    public function test_two_saves_in_the_same_second_reject_the_stale_request(): void
    {
        $this->travelTo(Carbon::parse('2026-10-25 12:00:00'));

        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'case_title' => 'Original title',
        ]);
        $originalVersion = $case->lock_version;
        $originalTimestamp = $case->updated_at->format('Y-m-d H:i:s');

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), $this->payload($case, $originalVersion, [
                'case_title' => 'First save',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($originalTimestamp, $case->fresh()->updated_at->format('Y-m-d H:i:s'));

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), $this->payload($case, $originalVersion, [
                'case_title' => 'Stale second save',
            ]))
            ->assertSessionHasErrors('lock_version');

        $case->refresh();
        $this->assertSame('First save', $case->case_title);
        $this->assertSame($originalVersion + 1, $case->lock_version);
        $this->assertSame(1, $case->auditLogs()->where('action_performed', AuditLog::ACTION_EDIT)->count());
    }

    public function test_a_successful_edit_increments_the_version_exactly_once(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $originalVersion = $case->lock_version;

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), $this->payload($case, $originalVersion, [
                'case_title' => 'Corrected title',
            ]))
            ->assertRedirect(route('cases.show', $case))
            ->assertSessionHasNoErrors();

        $case->refresh();
        $this->assertSame('Corrected title', $case->case_title);
        $this->assertSame($originalVersion + 1, $case->lock_version);
    }

    public function test_reassignment_invalidates_an_already_open_edit_form(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $replacement = User::factory()->investigator()->create();
        $case = CaseModel::factory()->create();
        $openFormVersion = $case->lock_version;

        $this->actingAs($supervisor)
            ->put(route('cases.reassign.update', $case), [
                'investigator_id' => $replacement->id,
                'docket_no' => $case->docket_no,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($openFormVersion + 1, $case->fresh()->lock_version);

        $this->actingAs($supervisor)
            ->put(route('cases.update', $case), $this->payload($case, $openFormVersion, [
                'case_title' => 'Stale supervisor edit',
                'reason' => 'Covering while the assigned investigator is on leave.',
            ]))
            ->assertSessionHasErrors('lock_version');

        $this->assertNotSame('Stale supervisor edit', $case->fresh()->case_title);
        $this->assertDatabaseMissing('audit_logs', [
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT_ON_BEHALF,
        ]);
    }

    public function test_a_rejected_stale_save_re_populates_the_form_with_the_users_input(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $staleVersion = $case->lock_version;

        $case->increment('lock_version');

        $this->actingAs($investigator)
            ->from(route('cases.edit', $case))
            ->put(route('cases.update', $case), $this->payload($case, $staleVersion, [
                'case_title' => 'My conflicting edit',
            ]))
            ->assertRedirect(route('cases.edit', $case))
            ->assertSessionHasInput('case_title', 'My conflicting edit');
    }

    public function test_an_edit_form_without_a_lock_version_is_rejected(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $payload = $this->payload($case, $case->lock_version);
        unset($payload['lock_version']);

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), $payload)
            ->assertSessionHasErrors('lock_version');

        $this->assertDatabaseMissing('audit_logs', [
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT,
        ]);
    }
}
