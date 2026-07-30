<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
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
        $this->middleware('guest');
        // The finding this closes named the missing gate specifically:
        // POST /register had no rate limit at all. Login already gets 5/min
        // for free from laravel/ui's ThrottlesLogins; registration needed
        // its own line since RegistersUsers carries no such trait.
        $this->middleware('throttle:5,1')->only('register');
    }

    /**
     * Show the registration form, with the case-handling roles a registrant
     * may pick between. Admin is deliberately not offered here — see
     * Role::selectableRoleRule().
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm()
    {
        return view('auth.register', ['roles' => Role::query()->whereIn('role_name', Role::CASE_HANDLING)->orderBy('role_name')->get()]);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:users'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'office_region' => ['required', 'string', 'max:255'],
            'role_id' => ['required', Role::selectableRoleRule()],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * The role is now the registrant's own choice (Investigator or
     * Supervisor — Role::selectableRoleRule() enforces the boundary,
     * Admin is never a reachable value here), but the account still can't do
     * anything with it until an Admin approves it: every new registration
     * lands `registration_status = pending`, which is also the column's own
     * default, so this line is redundant with intent rather than load-bearing.
     *
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'username' => $data['username'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'office_region' => $data['office_region'],
            'password' => Hash::make($data['password']),
            'role_id' => $data['role_id'],
            'registration_status' => User::REGISTRATION_PENDING,
        ]);
    }

    /**
     * RegistersUsers::register() logs the new user in before this hook
     * fires, but a pending account isn't supposed to have a session yet — so
     * undo that immediately and send them to the login screen with an
     * explanation instead of the dashboard.
     */
    protected function registered(Request $request, $user)
    {
        Auth::guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('status', __('Your account has been submitted and is awaiting administrator approval.'));
    }
}
