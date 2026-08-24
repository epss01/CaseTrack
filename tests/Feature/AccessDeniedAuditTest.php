<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The BOLA demonstration: an investigator probing a colleague's cases across
 * every case-scoped route shape gets 403, changes nothing, and — the part
 * that was previously undemonstrated — the denial itself lands in
 * audit_logs, naming the prober as actor.
 *
 * This is the committed, repeatable substitute for a live security-reviewer
 * browser run: no subagent can enter a password to authenticate as a real
 * account (a platform-level rule, not a project one), so this test probes
 * the same route surface via actingAs() instead. Same shape of proof —
 * "403, no state change, and it's recorded" — that runs in CI and catches
 * regressions a one-off session cannot.
 */
class AccessDeniedAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_probing_a_colleagues_case_across_every_route_403s_and_is_recorded(): void
    {
        $prober = User::factory()->investigator()->create();
        $owner = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($owner)->create([
            'docket_no' => 'DEMO-VIII-2026-0001',
            'status' => 'Under investigation',
        ]);
        $case->timeline()->create(['date_of_docket' => '2026-06-01']);

        $probes = [
            ['GET', "/cases/{$case->id}"],
            ['GET', "/cases/{$case->id}/edit"],
            ['GET', "/reports/cases/{$case->id}"],
            ['GET', "/cases/{$case->id}/timeline/edit"],
            ['GET', "/cases/{$case->id}/reassign"],
            ['GET', "/cases/{$case->id}/delete"],
        ];

        foreach ($probes as [$method, $uri]) {
            $this->actingAs($prober)->call($method, $uri)->assertForbidden();
        }

        $case->refresh();
        $this->assertSame($owner->id, $case->investigator_id);
        $this->assertSame('Under investigation', $case->status);

        $this->assertSame(count($probes), AuditLog::where('user_id', $prober->id)
            ->where('case_id', $case->id)
            ->where('action_performed', AuditLog::ACTION_ACCESS_DENIED)
            ->count());

        // No change action of any kind slipped through alongside the denials.
        $this->assertDatabaseMissing('audit_logs', ['action_performed' => AuditLog::ACTION_EDIT]);
        $this->assertDatabaseMissing('audit_logs', ['action_performed' => AuditLog::ACTION_DELETE]);
        $this->assertDatabaseMissing('audit_logs', ['action_performed' => AuditLog::ACTION_UPDATE]);
    }

    public function test_write_probes_against_a_colleagues_case_also_403_and_are_recorded(): void
    {
        $prober = User::factory()->investigator()->create();
        $owner = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($owner)->create([
            'status' => 'Under investigation',
        ]);
        $case->timeline()->create(['date_of_docket' => '2026-06-01']);

        $this->actingAs($prober)
            ->put("/cases/{$case->id}/reassign", ['investigator_id' => $prober->id, 'docket_no' => $case->docket_no])
            ->assertForbidden();

        $this->actingAs($prober)
            ->delete("/cases/{$case->id}")
            ->assertForbidden();

        $this->actingAs($prober)
            ->put("/cases/{$case->id}/closure")
            ->assertForbidden();

        $case->refresh();
        $this->assertSame($owner->id, $case->investigator_id);
        $this->assertNotSoftDeleted($case);
        $this->assertSame('Under investigation', $case->status);

        $this->assertSame(3, AuditLog::where('user_id', $prober->id)
            ->where('case_id', $case->id)
            ->where('action_performed', AuditLog::ACTION_ACCESS_DENIED)
            ->count());
    }

    /**
     * A denial with nothing case-scoped to name still gets recorded, with a
     * null case_id — the same precedent ACTION_EXPORTED and the rating-change
     * entry already set for actions that aren't about one case.
     */
    public function test_a_non_case_denial_is_recorded_with_a_null_case_id(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)->get('/admin/users')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $investigator->id,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_ACCESS_DENIED,
        ]);
    }

    public function test_a_guest_redirected_to_login_writes_no_denial(): void
    {
        $case = CaseModel::factory()->create();

        $this->get("/cases/{$case->id}")->assertRedirect('/login');

        // A guest has no identity to attribute a denial to, and never
        // reaches a 403 in the first place — the auth middleware redirects
        // first. Confirms the hook doesn't fire on that redirect.
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
