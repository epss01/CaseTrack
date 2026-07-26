<?php

use App\Http\Controllers\CaseController;
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
        ->only(['index', 'create', 'store', 'show', 'edit', 'update']);
});
