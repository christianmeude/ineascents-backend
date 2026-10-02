<?php

use App\Http\Controllers\Admin\BookingController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PackageController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\LegalController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware(['auth', 'admin'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['verified'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('packages', PackageController::class);
    Route::resource('bookings', BookingController::class);
    Route::resource('customers', CustomerController::class)->only(['index', 'show'])->parameters(['customers' => 'email']);
    Route::post('customers/link', [CustomerController::class, 'link'])->name('customers.link');
    Route::post('customers/unlink', [CustomerController::class, 'unlink'])->name('customers.unlink');
    Route::resource('inquiries', InquiryController::class)->only(['index', 'show', 'update']);
    Route::post('inquiries/{inquiry}/promote', [InquiryController::class, 'promote'])->name('inquiries.promote');
    Route::patch('bookings/{booking}/approve', [BookingController::class, 'approve'])->name('bookings.approve');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('calendar/toggle-block', [CalendarController::class, 'toggleBlock'])->name('calendar.toggle-block');
});

require __DIR__.'/auth.php';

// A17: versioned public legal pages (consent pins ?v=).
Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('terms');

// Root: health hint locally; redirect to FRONTEND_URL when configured.
// 12-Factor: FRONTEND_URL via config (not direct env() under cache).
Route::get('/', function () {
    $frontend = config('app.frontend_url');

    if (app()->environment('local') && empty($frontend)) {
        return response()->json([
            'app' => config('app.name'),
            'env' => app()->environment(),
            'message' => 'Backend is running. Set FRONTEND_URL to enable redirect, or use /api/ping for health.',
            'health' => url('/api/ping'),
            'admin' => url('/admin/dashboard'),
        ]);
    }

    if (empty($frontend)) {
        abort(404, 'FRONTEND_URL not configured for this environment.');
    }

    $target = trim(explode(',', $frontend)[0]);
    if ($target === '') {
        abort(404, 'FRONTEND_URL is empty.');
    }

    return redirect($target);
});

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::get('/status', fn () => response()->view('status', ['date' => '2026-10-02'], 200));
