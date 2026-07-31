<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Account management: activate/deactivate, role change, admin-performed
 * password reset. Closes "no way to disable a departing/compromised
 * account short of a direct database edit".
 */
class AdminUserAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admins_get_403_on_every_admin_route(): void
    {
        $investigator = User::factory()->investigator()->create();
        $target = User::factory()->investigator()->create();

        $this->actingAs($investigator)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($investigator)->put(route('admin.users.active', $target), ['is_active' => false])->assertForbidden();
        $this->actingAs($investigator)->put(route('admin.users.role', $target), ['role_id' => 1])->assertForbidden();
        $this->actingAs($investigator)->put(route('admin.users.password', $target), ['password' => 'x', 'password_confirmation' => 'x'])->assertForbidden();
    }

    public function test_deactivating_blocks_a_subsequent_login(): void
    {
        $admin = User::factory()->admin()->create();
        $investigator = User::factory()->investigator()->create(['username' => 'to-deactivate']);

        $this->actingAs($admin)
            ->put(route('admin.users.active', $investigator), ['is_active' => false])
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse($investigator->fresh()->is_active);

        // actingAs() leaves the admin authenticated for the rest of the
        // test; log out so the next request actually reaches the login
        // form's guest-only path instead of bouncing off it as the admin.
        $this->post('/logout');

        $this->post('/login', ['username' => 'to-deactivate', 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_ACCOUNT_DEACTIVATED,
        ]);
    }

    public function test_deactivation_logs_out_an_already_authenticated_session(): void
    {
        $admin = User::factory()->admin()->create();
        $investigator = User::factory()->investigator()->create();

        // The investigator is mid-session when the admin deactivates them.
        $this->actingAs($investigator)->get('/home')->assertOk();

        $investigator->update(['is_active' => false]);

        $this->get('/home')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reactivating_restores_login(): void
    {
        $admin = User::factory()->admin()->create();
        $investigator = User::factory()->investigator()->create([
            'username' => 'to-reactivate',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.users.active', $investigator), ['is_active' => true])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($investigator->fresh()->is_active);

        $this->post('/logout');

        $this->post('/login', ['username' => 'to-reactivate', 'password' => 'password'])
            ->assertRedirect('/home');
        $this->assertAuthenticatedAs($investigator);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_ACCOUNT_ACTIVATED,
        ]);
    }

    public function test_role_change_takes_effect_immediately(): void
    {
        $admin = User::factory()->admin()->create();
        $investigator = User::factory()->investigator()->create();
        $supervisorRole = Role::firstOrCreate(['role_name' => Role::SUPERVISOR]);

        // Supervisor-only route, blocked before the change.
        $this->actingAs($investigator)->get(route('workload.index'))->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.users.role', $investigator), ['role_id' => $supervisorRole->id])
            ->assertRedirect(route('admin.users.index'));

        $investigator->refresh();
        $this->assertSame(Role::SUPERVISOR, $investigator->role->role_name);

        $this->actingAs($investigator)->get(route('workload.index'))->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_ACCOUNT_ROLE_CHANGED,
        ]);
    }

    public function test_role_change_to_admin_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $investigator = User::factory()->investigator()->create();
        $adminRole = Role::firstOrCreate(['role_name' => Role::ADMIN]);

        $this->actingAs($admin)
            ->put(route('admin.users.role', $investigator), ['role_id' => $adminRole->id])
            ->assertSessionHasErrors('role_id');

        $this->assertSame(Role::INVESTIGATOR, $investigator->fresh()->role->role_name);
    }

    public function test_an_admin_row_cannot_be_role_changed(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();
        $investigatorRole = Role::firstOrCreate(['role_name' => Role::INVESTIGATOR]);

        $this->actingAs($admin)
            ->put(route('admin.users.role', $otherAdmin), ['role_id' => $investigatorRole->id])
            ->assertForbidden();

        $this->assertSame(Role::ADMIN, $otherAdmin->fresh()->role->role_name);
    }

    public function test_an_admin_cannot_act_on_their_own_account(): void
    {
        $admin = User::factory()->admin()->create();
        $investigatorRole = Role::firstOrCreate(['role_name' => Role::INVESTIGATOR]);

        $this->actingAs($admin)
            ->put(route('admin.users.active', $admin), ['is_active' => false])
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.users.role', $admin), ['role_id' => $investigatorRole->id])
            ->assertForbidden();

        $this->actingAs($admin)
            ->put(route('admin.users.password', $admin), ['password' => 'new-password', 'password_confirmation' => 'new-password'])
            ->assertForbidden();

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_password_reset_actually_changes_the_credential(): void
    {
        $admin = User::factory()->admin()->create();
        $investigator = User::factory()->investigator()->create(['username' => 'reset-me']);

        $this->actingAs($admin)
            ->put(route('admin.users.password', $investigator), [
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->post('/logout');

        $this->post('/login', ['username' => 'reset-me', 'password' => 'password'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->post('/login', ['username' => 'reset-me', 'password' => 'brand-new-password'])
            ->assertRedirect('/home');
        $this->assertAuthenticatedAs($investigator);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_ACCOUNT_PASSWORD_RESET,
        ]);
    }
}
