<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * make:admin — the only way an Admin account is provisioned. Had no test
 * until the account-creation audit site was added.
 */
class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_approved_active_admin_and_audits_its_own_creation(): void
    {
        $this->artisan('make:admin')
            ->expectsQuestion('Username', 'newadmin')
            ->expectsQuestion('First name', 'New')
            ->expectsQuestion('Last name', 'Admin')
            ->expectsQuestion('Password (min 8 characters)', 'secret-password')
            ->expectsQuestion('Confirm password', 'secret-password')
            ->assertSuccessful();

        $admin = User::where('username', 'newadmin')->firstOrFail();

        $this->assertSame(Role::ADMIN, $admin->role->role_name);
        $this->assertTrue($admin->is_active);
        $this->assertSame(User::REGISTRATION_APPROVED, $admin->registration_status);
        $this->assertTrue(Hash::check('secret-password', $admin->password));

        // Actor and target are the same account — there is no one else yet
        // to attribute its creation to.
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'target_user_id' => $admin->id,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_ACCOUNT_CREATED,
        ]);
    }

    public function test_it_rejects_a_duplicate_username_and_writes_no_audit_entry(): void
    {
        User::factory()->admin()->create(['username' => 'existing']);

        $this->artisan('make:admin')
            ->expectsQuestion('Username', 'existing')
            ->expectsQuestion('First name', 'New')
            ->expectsQuestion('Last name', 'Admin')
            ->expectsQuestion('Password (min 8 characters)', 'secret-password')
            ->expectsQuestion('Confirm password', 'secret-password')
            ->assertFailed();

        $this->assertSame(0, AuditLog::count());
    }
}
