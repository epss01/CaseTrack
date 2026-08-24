<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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

    /**
     * Reconnaissance-by-URL-probing was the finding; a successful login is
     * the other half of "who is doing what" — closes the audit trail's
     * complete absence of authentication events.
     */
    public function test_a_successful_login_is_audited(): void
    {
        $user = User::factory()->create(['username' => 'jdelacruz', 'password' => 'password']);

        $this->post('/login', ['username' => 'jdelacruz', 'password' => 'password'])
            ->assertRedirect('/home');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'target_user_id' => null,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_LOGIN,
        ]);
    }

    /**
     * A wrong password never resolves a user credentials() can attribute an
     * entry to — logging only the attributable subset (right username, wrong
     * password) would look like failed-login coverage without actually being
     * it, since every unknown/pending/rejected/deactivated username fails the
     * exact same way and none of those resolve a user either.
     */
    public function test_a_failed_login_writes_no_audit_entry(): void
    {
        User::factory()->create(['username' => 'jdelacruz', 'password' => 'password']);

        $this->post('/login', ['username' => 'jdelacruz', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('username');

        $this->assertSame(0, AuditLog::count());
    }

    /**
     * The reason ACTION_LOGIN is fired from a Login event listener rather
     * than a LoginController::authenticated() hook: a remember-me cookie
     * resumes a session (Guard::viaRemember()) without the controller
     * running at all, and that path still has to be audited.
     */
    public function test_a_remembered_session_resuming_without_a_form_login_is_audited(): void
    {
        $user = User::factory()->create();
        $user->setRememberToken($token = Str::random(60));
        $user->save();

        $recaller = $user->id.'|'.$token.'|'.$user->getAuthPassword();

        // A plain withCookie(), not withUnencryptedCookie(): EncryptCookies
        // tries to decrypt every cookie it doesn't explicitly exempt and
        // nulls it out on failure, so the cookie has to arrive encrypted
        // the same way a real remember-me cookie would — which is exactly
        // what withCookie() (unlike the "unencrypted" variant) does.
        $this->withCookie(Auth::guard('web')->getRecallerName(), $recaller)
            ->get('/home')
            ->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action_performed' => AuditLog::ACTION_LOGIN,
        ]);
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

        // Actor and target are the same user — a registrant acts alone.
        // No ACTION_LOGIN entry alongside it: RegistersUsers logs the new
        // account in before ::registered() immediately logs it back out,
        // and a pending account's momentary session is guarded out of
        // ACTION_LOGIN specifically because it never survives the request.
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'target_user_id' => $user->id,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_ACCOUNT_CREATED,
        ]);
        $this->assertDatabaseMissing('audit_logs', ['action_performed' => AuditLog::ACTION_LOGIN]);
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

    /**
     * ThrottlesLogins (via AuthenticatesUsers on LoginController) caps failed
     * attempts at 5 before locking out — no throttle: route middleware
     * needed, the trait already keys on username+IP. The 6th attempt is
     * blocked before the password is even checked.
     */
    public function test_repeated_failed_logins_are_locked_out(): void
    {
        User::factory()->create(['username' => 'jdelacruz', 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'jdelacruz', 'password' => 'wrong-password'])
                ->assertSessionHasErrors('username');
        }

        $this->post('/login', ['username' => 'jdelacruz', 'password' => 'password'])
            ->assertStatus(302)
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    /**
     * The throttle key is username+IP (ThrottlesLogins::throttleKey()), not
     * IP alone — a lockout on one username must not block a different
     * username from the same client.
     */
    public function test_a_lockout_on_one_username_does_not_block_another(): void
    {
        User::factory()->create(['username' => 'jdelacruz', 'password' => 'password']);
        User::factory()->create(['username' => 'mreyes', 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'jdelacruz', 'password' => 'wrong-password']);
        }

        $response = $this->post('/login', ['username' => 'mreyes', 'password' => 'password']);

        $response->assertRedirect('/home');
        $this->assertAuthenticatedAs(User::where('username', 'mreyes')->first());
    }

    /**
     * A correct password before the 5-attempt cap clears the counter
     * (ThrottlesLogins::clearLoginAttempts() in sendLoginResponse()) — a
     * legitimate user who mistypes twice isn't penalized afterward.
     */
    public function test_a_successful_login_clears_prior_failed_attempts(): void
    {
        $user = User::factory()->create(['username' => 'jdelacruz', 'password' => 'password']);

        $this->post('/login', ['username' => 'jdelacruz', 'password' => 'wrong-password']);
        $this->post('/login', ['username' => 'jdelacruz', 'password' => 'wrong-password']);

        $this->post('/login', ['username' => 'jdelacruz', 'password' => 'password'])
            ->assertRedirect('/home');

        $this->assertAuthenticatedAs($user);
    }

    /**
     * A lockout against a real, approved+active username is audited so a
     * password-guessing run against a known account is visible in the
     * trail — see ACTION_LOGIN_LOCKOUT's docblock for why this is
     * deliberately the only case that gets recorded.
     */
    public function test_a_lockout_against_a_real_account_is_audited(): void
    {
        $user = User::factory()->create(['username' => 'jdelacruz', 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'jdelacruz', 'password' => 'wrong-password']);
        }
        $this->post('/login', ['username' => 'jdelacruz', 'password' => 'password']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'target_user_id' => null,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_LOGIN_LOCKOUT,
        ]);
    }

    /**
     * An unknown username resolves no user for the Lockout listener to
     * attribute an entry to, same reasoning as failed logins generally —
     * logging only the attributable sliver would misrepresent coverage.
     */
    public function test_a_lockout_against_an_unknown_username_writes_no_audit_entry(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['username' => 'nonexistent', 'password' => 'wrong-password']);
        }
        $this->post('/login', ['username' => 'nonexistent', 'password' => 'wrong-password']);

        $this->assertDatabaseMissing('audit_logs', ['action_performed' => AuditLog::ACTION_LOGIN_LOCKOUT]);
    }
}
