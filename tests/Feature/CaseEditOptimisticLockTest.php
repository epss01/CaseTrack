<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A stale `updated_at` on the edit form means someone else saved the case
 * first — reject the save instead of silently overwriting their change.
 */
class CaseEditOptimisticLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stale_save_is_rejected_and_does_not_overwrite_the_newer_one(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'case_title' => 'Original title',
        ]);

        $staleStamp = $case->updated_at->format('Y-m-d H:i:s');

        // Someone else saves first (a second later, so the timestamp compare
        // has something to actually catch).
        $this->travel(1)->seconds();
        $case->update(['case_title' => 'Updated by someone else']);

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), [
                'case_title' => 'My conflicting edit',
                'incident_details' => $case->incident_details,
                'status' => $case->status,
                'complexity_weight' => $case->complexity_weight,
                'updated_at' => $staleStamp,
            ])
            ->assertSessionHasErrors('updated_at');

        $this->assertSame('Updated by someone else', $case->fresh()->case_title);
        $this->assertDatabaseMissing('audit_logs', [
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_EDIT,
        ]);
    }

    public function test_a_save_with_the_current_stamp_succeeds(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), [
                'case_title' => 'Corrected title',
                'incident_details' => $case->incident_details,
                'status' => $case->status,
                'complexity_weight' => $case->complexity_weight,
                'updated_at' => $case->updated_at->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('cases.show', $case))
            ->assertSessionHasNoErrors();

        $this->assertSame('Corrected title', $case->fresh()->case_title);
    }

    public function test_a_rejected_stale_save_re_populates_the_form_with_the_users_input(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $staleStamp = $case->updated_at->format('Y-m-d H:i:s');
        $this->travel(1)->seconds();
        $case->update(['case_title' => 'Updated by someone else']);

        $this->actingAs($investigator)
            ->from(route('cases.edit', $case))
            ->put(route('cases.update', $case), [
                'case_title' => 'My conflicting edit',
                'incident_details' => $case->incident_details,
                'status' => $case->status,
                'complexity_weight' => $case->complexity_weight,
                'updated_at' => $staleStamp,
            ])
            ->assertRedirect(route('cases.edit', $case))
            ->assertSessionHasInput('case_title', 'My conflicting edit');
    }
}
