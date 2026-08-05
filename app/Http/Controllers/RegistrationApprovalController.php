<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The registration-approval gate. Admin-only, enforced by the role:Admin
 * middleware on the route group — nothing here is scoped to an individual
 * case, so (like /workload) there's no policy class.
 */
class RegistrationApprovalController extends Controller
{
    public function index()
    {
        $pending = User::query()
            ->where('registration_status', User::REGISTRATION_PENDING)
            ->with('role')
            ->orderBy('created_at')
            ->get();

        return view('admin.registrations', ['pending' => $pending]);
    }

    /**
     * Approve a registration. Sets approved_by/approved_at so approval is
     * traceable independent of the general audit trail.
     */
    public function approve(Request $request, User $user)
    {
        DB::transaction(function () use ($request, $user) {
            $user->update([
                'registration_status' => User::REGISTRATION_APPROVED,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);

            AuditLog::record($request->user(), null, AuditLog::ACTION_REGISTRATION_APPROVED, $user);
        });

        return redirect()
            ->route('admin.registrations.index')
            ->with('status', __('Approved :name.', ['name' => $user->full_name]));
    }

    /**
     * Reject a registration. Only flips the status — no account deletion is
     * implied, and audit_logs.user_id has no delete path anyway.
     */
    public function reject(Request $request, User $user)
    {
        DB::transaction(function () use ($request, $user) {
            $user->update(['registration_status' => User::REGISTRATION_REJECTED]);

            AuditLog::record($request->user(), null, AuditLog::ACTION_REGISTRATION_REJECTED, $user);
        });

        return redirect()
            ->route('admin.registrations.index')
            ->with('status', __('Rejected :name.', ['name' => $user->full_name]));
    }
}
