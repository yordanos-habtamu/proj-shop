<?php

use App\Http\Controllers\Admin\FeeController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\ConnectController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\StorefrontController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function () {
        Route::get('fees', [FeeController::class, 'index'])->name('fees.index');
        Route::post('fees', [FeeController::class, 'update'])->name('fees.update');
    });

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

    Route::post('projects/{project}/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('demo/checkout/{order}', [CheckoutController::class, 'demo'])->name('checkout.demo');
    Route::post('demo/checkout/{order}/pay', [CheckoutController::class, 'demoPay'])->name('checkout.demo-pay');

    Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('connect', [ConnectController::class, 'show'])->name('connect.show');
    Route::post('connect/start', [ConnectController::class, 'start'])->name('connect.start');
    Route::get('connect/callback', [ConnectController::class, 'callback'])->name('connect.callback');
});

Route::prefix('projects')->name('projects.')->group(function () {
    Route::get('', [StorefrontController::class, 'index'])->name('index');
    Route::get('{project}/cover', [StorefrontController::class, 'cover'])->name('cover');
    Route::get('{project:slug}', [StorefrontController::class, 'show'])->name('show');
});

Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle'])->name('webhooks.stripe');

require __DIR__.'/settings.php';
