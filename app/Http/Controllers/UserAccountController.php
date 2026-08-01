<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountPasswordRequest;
use App\Http\Requests\UpdateAccountRoleRequest;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Identity/access management — activate/deactivate, role change, password
 * reset. Admin-only via role:Admin middleware, no policy class, same
 * convention as /workload: nothing here decides per-case access.
 *
 * Every mutating action refuses to target the acting admin's own account
 * (no self-lockout, no self-promotion) and refuses an Admin row as a role
 * target (Admin accounts are listed for visibility but not self-actionable
 * from this screen — provisioning is make:admin only).
 */
class UserAccountController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->with('role')
            ->where('registration_status', User::REGISTRATION_APPROVED)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $roles = Role::query()->whereIn('role_name', Role::CASE_HANDLING)->orderBy('role_name')->get();

        return view('admin.users', ['users' => $users, 'roles' => $roles]);
    }

    public function updateActive(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 403);

        $validated = $request->validate(['is_active' => ['required', 'boolean']]);

        DB::transaction(function () use ($request, $user, $validated) {
            $user->update(['is_active' => $validated['is_active']]);

            AuditLog::record(
                $request->user(),
                null,
                $validated['is_active'] ? AuditLog::ACTION_ACCOUNT_ACTIVATED : AuditLog::ACTION_ACCOUNT_DEACTIVATED,
                $user
            );
        });

        return redirect()
            ->route('admin.users.index')
            ->with('status', __(':name updated.', ['name' => $user->full_name]));
    }

    public function updateRole(UpdateAccountRoleRequest $request, User $user)
    {
        abort_if($user->is($request->user()) || $user->isAdmin(), 403);

        DB::transaction(function () use ($request, $user) {
            $user->update($request->validated());

            AuditLog::record($request->user(), null, AuditLog::ACTION_ACCOUNT_ROLE_CHANGED, $user);
        });

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('Role updated for :name.', ['name' => $user->full_name]));
    }

    public function updatePassword(UpdateAccountPasswordRequest $request, User $user)
    {
        abort_if($user->is($request->user()), 403);

        DB::transaction(function () use ($request, $user) {
            // The model's `hashed` cast makes this idempotent-safe the same
            // way RegisterController::create() relies on — assign the plain
            // value, Eloquent hashes it on save.
            $user->update(['password' => $request->validated('password')]);

            AuditLog::record($request->user(), null, AuditLog::ACTION_ACCOUNT_PASSWORD_RESET, $user);
        });

        return redirect()
            ->route('admin.users.index')
            ->with('status', __('Password reset for :name.', ['name' => $user->full_name]));
    }
}
