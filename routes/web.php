<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseTimelineController;
use App\Http\Controllers\WorkloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

// Users have no email address, so the email-based password reset and
// verification flows are disabled. Password confirmation is still registered.
Auth::routes(['reset' => false, 'verify' => false]);

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

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
});

// The workload picture, and where performance ratings are set. Supervisor-only
// throughout, so the middleware carries it — there is no per-case decision here
// for CaseModelPolicy to make.
Route::middleware(['auth', 'role:Supervisor'])->group(function () {
    Route::get('workload', [WorkloadController::class, 'index'])->name('workload.index');
    Route::put('workload/{investigator}', [WorkloadController::class, 'update'])
        ->name('workload.update');
});
