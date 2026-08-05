<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RotatePasswordCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rotates_password_remember_token_and_sessions_for_named_users(): void
    {
        $actor = User::factory()->supervisor()->create(['username' => 'supervisor']);
        $userA = User::factory()->create(['username' => 'jdelacruz', 'password' => 'password']);
        $userB = User::factory()->create(['username' => 'abautista', 'password' => 'password']);

        $oldTokenA = $userA->remember_token;

        DB::table('sessions')->insert([
            'id' => 'sess-a',
            'user_id' => $userA->id,
            'payload' => 'x',
            'last_activity' => time(),
        ]);

        $this->artisan('users:rotate-password', [
            'username' => ['jdelacruz', 'abautista'],
            '--actor' => 'supervisor',
        ])->assertSuccessful();

        $userA->refresh();
        $userB->refresh();

        $this->assertFalse(Hash::check('password', $userA->password));
        $this->assertFalse(Hash::check('password', $userB->password));
        $this->assertNotSame($userA->password, $userB->password);
        $this->assertNotSame($oldTokenA, $userA->remember_token);

        $this->assertDatabaseMissing('sessions', ['user_id' => $userA->id]);

        $this->assertSame(2, AuditLog::where('action_performed', AuditLog::ACTION_ACCOUNT_PASSWORD_RESET)
            ->where('user_id', $actor->id)
            ->count());

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'target_user_id' => $userA->id,
            'action_performed' => AuditLog::ACTION_ACCOUNT_PASSWORD_RESET,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $actor->id,
            'target_user_id' => $userB->id,
            'action_performed' => AuditLog::ACTION_ACCOUNT_PASSWORD_RESET,
        ]);
    }

    public function test_it_requires_a_valid_actor(): void
    {
        $user = User::factory()->create(['username' => 'jdelacruz', 'password' => 'password']);

        $this->artisan('users:rotate-password', [
            'username' => ['jdelacruz'],
            '--actor' => 'does-not-exist',
        ])->assertFailed();

        $user->refresh();

        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertSame(0, AuditLog::count());
    }

    public function test_it_requires_usernames_or_all(): void
    {
        User::factory()->supervisor()->create(['username' => 'supervisor']);

        $this->artisan('users:rotate-password', [
            '--actor' => 'supervisor',
        ])->assertFailed();

        $this->assertSame(0, AuditLog::count());
    }

    public function test_all_rotates_every_account(): void
    {
        $actor = User::factory()->supervisor()->create(['username' => 'supervisor']);
        User::factory()->count(3)->create();

        $this->artisan('users:rotate-password', ['--all' => true, '--actor' => 'supervisor'])
            ->assertSuccessful();

        $this->assertSame(4, AuditLog::where('action_performed', AuditLog::ACTION_ACCOUNT_PASSWORD_RESET)->count());
    }
}
