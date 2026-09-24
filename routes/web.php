<?php

use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('mine', [ProjectController::class, 'index'])->name('mine');
        Route::get('create', [ProjectController::class, 'create'])->name('create');
        Route::post('', [ProjectController::class, 'store'])->name('store');
        Route::get('{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::match(['put', 'patch'], '{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('{project}', [ProjectController::class, 'destroy'])->name('destroy');
        Route::post('{project}/submit-for-review', [ProjectController::class, 'submitForReview'])->name('submit-for-review');

        Route::middleware('reviewer')->group(function () {
            Route::get('review', [ReviewController::class, 'index'])->name('review');
            Route::post('{project}/approve', [ReviewController::class, 'approve'])->name('approve');
            Route::post('{project}/reject', [ReviewController::class, 'reject'])->name('reject');
        });
    });
});

require __DIR__.'/settings.php';
