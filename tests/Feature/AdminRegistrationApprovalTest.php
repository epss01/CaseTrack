<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registration approval: the gate that closes "self-registration lands
 * anyone as Investigator with no approval step".
 */
class AdminRegistrationApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_pending_account_cannot_authenticate(): void
    {
        $user = User::factory()->investigator()->create([
            'username' => 'pending-user',
            'registration_status' => User::REGISTRATION_PENDING,
        ]);

        $this->post('/login', ['username' => 'pending-user', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
        $this->assertNotNull($user);
    }

    public function test_a_rejected_account_cannot_authenticate(): void
    {
        User::factory()->investigator()->create([
            'username' => 'rejected-user',
            'registration_status' => User::REGISTRATION_REJECTED,
        ]);

        $this->post('/login', ['username' => 'rejected-user', 'password' => 'password'])
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_an_approved_account_can_authenticate(): void
    {
        $user = User::factory()->investigator()->create(['username' => 'approved-user']);

        $this->post('/login', ['username' => 'approved-user', 'password' => 'password'])
            ->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_lands_pending_with_the_selected_role_and_does_not_log_in(): void
    {
        $role = Role::firstOrCreate(['role_name' => Role::SUPERVISOR]);

        $this->post('/register', [
            'username' => 'newsup',
            'first_name' => 'New',
            'last_name' => 'Supervisor',
            'office_region' => 'CHR Region VIII',
            'role_id' => $role->id,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect('/login');

        $this->assertGuest();

        $user = User::where('username', 'newsup')->firstOrFail();
        $this->assertSame(User::REGISTRATION_PENDING, $user->registration_status);
        $this->assertSame(Role::SUPERVISOR, $user->role->role_name);
        $this->assertNull($user->approved_by);
        $this->assertNull($user->approved_at);
    }

    public function test_a_registrant_cannot_select_the_admin_role(): void
    {
        $admin = Role::firstOrCreate(['role_name' => Role::ADMIN]);

        $this->post('/register', [
            'username' => 'sneaky',
            'first_name' => 'Sneaky',
            'last_name' => 'Registrant',
            'office_region' => 'CHR Region VIII',
            'role_id' => $admin->id,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('role_id');

        $this->assertNull(User::where('username', 'sneaky')->first());
    }

    public function test_non_admins_get_403_on_every_admin_route(): void
    {
        $investigator = User::factory()->investigator()->create();
        $supervisor = User::factory()->supervisor()->create();
        $pending = User::factory()->investigator()->create(['registration_status' => User::REGISTRATION_PENDING]);

        foreach ([$investigator, $supervisor] as $actor) {
            $this->actingAs($actor)->get(route('admin.registrations.index'))->assertForbidden();
            $this->actingAs($actor)->put(route('admin.registrations.approve', $pending))->assertForbidden();
            $this->actingAs($actor)->put(route('admin.registrations.reject', $pending))->assertForbidden();
        }
    }

    public function test_a_guest_is_redirected_to_login_from_the_admin_routes(): void
    {
        $this->get(route('admin.registrations.index'))->assertRedirect('/login');
    }

    public function test_approve_sets_status_and_approver_and_writes_one_audit_row(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->investigator()->create(['registration_status' => User::REGISTRATION_PENDING]);

        $this->actingAs($admin)
            ->put(route('admin.registrations.approve', $pending))
            ->assertRedirect(route('admin.registrations.index'));

        $pending->refresh();
        $this->assertSame(User::REGISTRATION_APPROVED, $pending->registration_status);
        $this->assertSame($admin->id, $pending->approved_by);
        $this->assertNotNull($pending->approved_at);

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_REGISTRATION_APPROVED,
        ]);
    }

    public function test_reject_only_flips_status_and_writes_one_audit_row(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = User::factory()->investigator()->create(['registration_status' => User::REGISTRATION_PENDING]);

        $this->actingAs($admin)
            ->put(route('admin.registrations.reject', $pending))
            ->assertRedirect(route('admin.registrations.index'));

        $pending->refresh();
        $this->assertSame(User::REGISTRATION_REJECTED, $pending->registration_status);
        $this->assertNull($pending->approved_by);

        // Rejecting never deletes the account.
        $this->assertDatabaseHas('users', ['id' => $pending->id]);

        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_REGISTRATION_REJECTED,
        ]);
    }

    public function test_the_register_route_is_rate_limited(): void
    {
        $role = Role::firstOrCreate(['role_name' => Role::INVESTIGATOR]);

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/register', [
                'username' => "user{$i}",
                'first_name' => 'Test',
                'last_name' => 'User',
                'office_region' => 'CHR Region VIII',
                'role_id' => $role->id,
                'password' => 'secret-password',
                'password_confirmation' => 'secret-password',
            ]);

            $this->assertNotEquals(429, $response->getStatusCode());
        }

        $this->post('/register', [
            'username' => 'user6',
            'first_name' => 'Test',
            'last_name' => 'User',
            'office_region' => 'CHR Region VIII',
            'role_id' => $role->id,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertStatus(429);
    }
}
