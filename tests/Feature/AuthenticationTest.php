<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Username');
    }

    public function test_users_can_authenticate_with_their_username(): void
    {
        $user = User::factory()->create(['username' => 'jdelacruz', 'password' => 'password']);

        $response = $this->post('/login', [
            'username' => 'jdelacruz',
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/home');
    }

    public function test_users_cannot_authenticate_with_an_invalid_password(): void
    {
        User::factory()->create(['username' => 'jdelacruz']);

        $this->post('/login', [
            'username' => 'jdelacruz',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_users_can_register_but_land_pending_approval(): void
    {
        $role = Role::firstOrCreate(['role_name' => Role::INVESTIGATOR]);

        $response = $this->post('/register', [
            'username' => 'jdelacruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'office_region' => 'CHR Region VIII',
            'role_id' => $role->id,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);

        // A pending registration is never logged in — it awaits an Admin's
        // approval before it can authenticate at all.
        $response->assertRedirect('/login');
        $this->assertGuest();

        $user = User::where('username', 'jdelacruz')->firstOrFail();

        $this->assertSame('Juan Dela Cruz', $user->full_name);
        $this->assertSame(Role::INVESTIGATOR, $user->role->role_name);
        $this->assertSame(User::REGISTRATION_PENDING, $user->registration_status);
        $this->assertFalse($user->is_staff);
    }

    public function test_registration_cannot_select_the_admin_role(): void
    {
        $admin = Role::firstOrCreate(['role_name' => Role::ADMIN]);

        $this->post('/register', [
            'username' => 'jdelacruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'office_region' => 'CHR Region VIII',
            'role_id' => $admin->id,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('role_id');

        $this->assertGuest();
        $this->assertNull(User::where('username', 'jdelacruz')->first());
    }

    public function test_registration_requires_a_unique_username(): void
    {
        User::factory()->create(['username' => 'jdelacruz']);
        $role = Role::firstOrCreate(['role_name' => Role::INVESTIGATOR]);

        $this->post('/register', [
            'username' => 'jdelacruz',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'office_region' => 'CHR Region VIII',
            'role_id' => $role->id,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_authenticated_users_can_reach_the_dashboard_and_log_out(): void
    {
        $user = User::factory()->create(['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);

        $this->actingAs($user)->get('/home')->assertOk()->assertSee('Juan Dela Cruz');

        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_email_based_password_reset_routes_are_not_registered(): void
    {
        $this->get('/password/reset')->assertNotFound();
        $this->get('/email/verify')->assertNotFound();
    }
}
