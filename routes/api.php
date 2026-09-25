<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/availability', [AvailabilityController::class, 'index']);

Route::get('/packages', [PackageController::class, 'index']);
Route::get('/packages/{package}', [PackageController::class, 'show']);

Route::post('/inquiries', [\App\Http\Controllers\Api\InquiryController::class, 'store'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/user', [ProfileController::class, 'update']);
    Route::post('/user/email/verify', [ProfileController::class, 'verifyEmail']);
    Route::post('/user/email/resend', [ProfileController::class, 'resendEmailCode']);

    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
});

// Ping endpoint for health checks (decoupled CI test)
Route::get('/ping', fn() => 'pong');

Route::post('/webhooks/paymongo', [\App\Http\Controllers\Api\PayMongoWebhookController::class, 'handle'])->middleware('throttle:60,1');

Route::post('/bookings/expire', function (Request $request) {
    if ($request->header('X-Cron-Token') !== env('CRON_TOKEN')) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $expired = \App\Models\Booking::expireStalePending();

    if ($expired > 0) {
        app(\App\Services\AdminNotifier::class)->alert(
            'booking.expired',
            'Stale bookings expired',
            "{$expired} unpaid booking(s) passed the hold window and were cancelled.",
            route('admin.bookings.index'),
        );
    }

    return response()->json(['message' => "Expired {$expired} bookings."]);
});
