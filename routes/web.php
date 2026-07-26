<?php

use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseTimelineController;
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

    // The timeline milestones are filled in after intake, as they happen.
    Route::get('cases/{case}/timeline/edit', [CaseTimelineController::class, 'edit'])
        ->name('cases.timeline.edit');
    Route::put('cases/{case}/timeline', [CaseTimelineController::class, 'update'])
        ->name('cases.timeline.update');
});
