<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditLogFilterRequest;
use App\Models\AuditLog;
use App\Models\User;

/**
 * Read-only view onto the audit trail. Admin-only via role:Admin middleware,
 * no policy class — same convention as /admin/registrations and
 * /admin/users: nothing here is a per-case decision.
 */
class AuditLogController extends Controller
{
    public function index(AuditLogFilterRequest $request)
    {
        $filters = $request->filters();

        $logs = AuditLog::query()
            ->with(['user', 'targetUser', 'case'])
            ->filteredBy($filters)
            ->latest('timestamp')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('admin.audit-logs', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::ACTIONS,
            'users' => User::query()->orderBy('last_name')->orderBy('first_name')->get(),
        ]);
    }
}
