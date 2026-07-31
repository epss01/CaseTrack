<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    /**
     * Get the login username to be used by the controller.
     *
     * Users are identified by username; the schema has no email column.
     *
     * @return string
     */
    public function username()
    {
        return 'username';
    }

    /**
     * A pending, rejected, or deactivated account cannot authenticate at
     * all — the extra keys become query constraints on top of the username
     * (EloquentUserProvider::retrieveByCredentials() treats every non-password
     * key that way), so a disqualified account fails the same generic
     * "these credentials do not match" check a wrong password gets. No
     * separate message, so a probe can't use it to tell a real pending
     * username from one that doesn't exist.
     *
     * @return array<string, mixed>
     */
    protected function credentials(Request $request)
    {
        return $request->only($this->username(), 'password') + [
            'registration_status' => User::REGISTRATION_APPROVED,
            'is_active' => true,
        ];
    }
}
