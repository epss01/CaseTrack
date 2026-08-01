<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The audit log viewer at /admin/audit-logs: Admin-only via role:Admin
 * middleware, no policy class — same convention as /admin/registrations
 * and /admin/users. Closes "nothing reads audit_logs".
 */
class AuditLogViewerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_view_the_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $someone = User::factory()->investigator()->create();
        AuditLog::record($someone, null, AuditLog::ACTION_LOGIN);

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee(AuditLog::ACTION_LOGIN)
            ->assertSee($someone->full_name);
    }

    public function test_non_admins_get_403(): void
    {
        $investigator = User::factory()->investigator()->create();
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($investigator)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.audit-logs.index'))->assertForbidden();
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.audit-logs.index'))->assertRedirect('/login');
    }

    public function test_filtering_by_acting_user_narrows_the_results(): void
    {
        $admin = User::factory()->admin()->create();
        $userA = User::factory()->investigator()->create();
        $userB = User::factory()->investigator()->create();
        AuditLog::record($userA, null, AuditLog::ACTION_LOGIN);
        AuditLog::record($userB, null, AuditLog::ACTION_LOGIN);

        // Not assertSee/assertDontSee on the page: the filter form's own
        // <select> lists every user's name as an option regardless of which
        // one is currently selected, so it would "contain" both names either
        // way. Assert on the filtered rows themselves instead.
        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['user_id' => $userA->id]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->first()->user_id === $userA->id);
    }

    public function test_filtering_by_action_narrows_the_results(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->investigator()->create();
        AuditLog::record($user, null, AuditLog::ACTION_LOGIN);
        AuditLog::record($user, null, AuditLog::ACTION_ACCESS_DENIED);

        // Same reasoning as the acting-user filter test: the action <select>
        // lists every action as an option, so assert on the rows, not the
        // page text.
        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['action' => AuditLog::ACTION_LOGIN]))
            ->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1
                && $logs->first()->action_performed === AuditLog::ACTION_LOGIN);
    }

    public function test_an_unknown_action_value_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['action' => 'NOT_A_REAL_ACTION']))
            ->assertSessionHasErrors('action');
    }

    public function test_filtering_by_date_range_narrows_the_results(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->investigator()->create();

        $old = AuditLog::record($user, null, AuditLog::ACTION_LOGIN);
        $old->timestamp = '2026-01-01 08:00:00';
        $old->save();

        $recent = AuditLog::record($user, null, AuditLog::ACTION_LOGIN);
        $recent->timestamp = '2026-07-01 08:00:00';
        $recent->save();

        $response = $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['from' => '2026-06-01']))
            ->assertOk();

        $response->assertViewHas('logs', fn ($logs) => $logs->total() === 1);
    }

    public function test_pagination_preserves_the_filter_query_string(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->investigator()->create();

        // No AuditLogFactory exists — record() itself is the only writer.
        for ($i = 0; $i < 30; $i++) {
            AuditLog::record($user, null, AuditLog::ACTION_LOGIN);
        }

        $response = $this->actingAs($admin)
            ->get(route('admin.audit-logs.index', ['action' => AuditLog::ACTION_LOGIN, 'page' => 2]))
            ->assertOk();

        $response->assertSee('action='.AuditLog::ACTION_LOGIN, false);
    }
}
