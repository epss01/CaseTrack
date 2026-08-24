<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseImportController;
use App\Http\Controllers\CaseTimelineController;
use App\Http\Controllers\RegistrationApprovalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\UserAccountController;
use App\Http\Controllers\WorkloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Users have no email address, so the email-based password reset and
// verification flows are disabled. Password confirmation is still registered.
Auth::routes(['reset' => false, 'verify' => false]);

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// Bulk case intake (CSV/XLSX) — Supervisor-only, unlike the general case
// routes below: import creates cases and assigns investigators, and reads as
// bulk data entry rather than ordinary casework (CLAUDE.md, Registration
// approval and account management / Roles). Registered before
// Route::resource('cases', ...) below: GET cases/import is the same
// two-segment shape as GET cases/{case} (show), and Laravel matches route
// definitions in registration order, so the wildcard would otherwise swallow
// "import" as a case id first.
Route::middleware(['auth', 'role:Supervisor'])->prefix('cases')->name('cases.')->group(function () {
    Route::get('import', [CaseImportController::class, 'create'])->name('import.create');
    Route::post('import/preview', [CaseImportController::class, 'preview'])->name('import.preview');
    Route::post('import', [CaseImportController::class, 'store'])->name('import.store');
});

// The middleware keeps anyone without a case-handling role out entirely;
// CaseModelPolicy then decides which individual cases are reachable.
Route::middleware(['auth', 'role:Investigator,Supervisor'])->group(function () {
    Route::resource('cases', CaseController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

    // Supervisor-only, enforced by CaseModelPolicy::delete()/reassign().
    // Deletion goes through a confirmation page rather than a JS dialog so the
    // confirmation step survives with scripting off.
    Route::get('cases/{case}/delete', [CaseController::class, 'confirmDelete'])
        ->name('cases.confirm-delete');
    Route::get('cases/{case}/reassign', [CaseController::class, 'reassignForm'])
        ->name('cases.reassign.edit');
    Route::put('cases/{case}/reassign', [CaseController::class, 'reassign'])
        ->name('cases.reassign.update');

    // Maker-checker on case closure: the assigned investigator proposes, a
    // supervisor decides. CaseModelPolicy::proposeClosure()/resolveClosure()
    // split the two halves; the decision runs through a confirmation page for
    // the same reason deletion does.
    Route::put('cases/{case}/closure', [CaseController::class, 'proposeClosure'])
        ->name('cases.closure.propose');
    Route::get('cases/{case}/closure', [CaseController::class, 'closureReview'])
        ->name('cases.closure.review');
    Route::put('cases/{case}/closure/resolve', [CaseController::class, 'resolveClosure'])
        ->name('cases.closure.resolve');

    // The timeline milestones are filled in after intake, as they happen.
    Route::get('cases/{case}/timeline/edit', [CaseTimelineController::class, 'edit'])
        ->name('cases.timeline.edit');
    Route::put('cases/{case}/timeline', [CaseTimelineController::class, 'update'])
        ->name('cases.timeline.update');

    // The statutory deadline countdowns. Scoped by CaseModel::scopeVisibleTo()
    // exactly as /cases is, and like /workload it is a list page with no
    // per-case decision to make, so the middleware carries it alone.
    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');

    // The case listing, filtered and exportable. Scoped by
    // CaseModel::scopeVisibleTo() like /cases and /alerts, which is also what
    // makes the investigator-level and office-wide reports one route: the
    // audience follows from who is asking. A list page with no per-case
    // decision, so the middleware carries it alone.
    // export is registered first so it is matched as its own path rather than
    // read as a report identifier.
    Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');

    // The one report that resolves a single case, so the one gated by
    // CaseModelPolicy::view() as well as by the middleware.
    Route::get('reports/cases/{case}', [ReportController::class, 'show'])->name('reports.show');
});

// The workload picture, and where performance ratings are set. Supervisor-only
// throughout, so the middleware carries it — there is no per-case decision here
// for CaseModelPolicy to make.
Route::middleware(['auth', 'role:Supervisor'])->group(function () {
    Route::get('workload', [WorkloadController::class, 'index'])->name('workload.index');
    Route::put('workload/{investigator}', [WorkloadController::class, 'update'])
        ->name('workload.update');
});

// Identity/access administration: registration approval and account
// management. Admin-only, middleware alone carries it — like /workload,
// nothing here is a per-case decision for CaseModelPolicy to make.
Route::middleware(['auth', 'role:Admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('registrations', [RegistrationApprovalController::class, 'index'])->name('registrations.index');
    Route::put('registrations/{user}/approve', [RegistrationApprovalController::class, 'approve'])->name('registrations.approve');
    Route::put('registrations/{user}/reject', [RegistrationApprovalController::class, 'reject'])->name('registrations.reject');

    Route::get('users', [UserAccountController::class, 'index'])->name('users.index');
    Route::put('users/{user}/active', [UserAccountController::class, 'updateActive'])->name('users.active');
    Route::put('users/{user}/role', [UserAccountController::class, 'updateRole'])->name('users.role');
    Route::put('users/{user}/password', [UserAccountController::class, 'updatePassword'])->name('users.password');

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
