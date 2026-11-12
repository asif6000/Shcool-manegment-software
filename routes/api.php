<?php

use App\Http\Controllers\Api\V1\AttendanceDeviceEventController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DevicePushTokenController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SchoolController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:api-login')
        ->name('auth.login');

    // Biometric terminals authenticate with their own device secret, never a
    // user session. The endpoint answers 503 while no adapter is configured.
    Route::post('/attendance/events', AttendanceDeviceEventController::class)
        ->middleware('throttle:attendance-device')
        ->name('attendance.events');

    Route::middleware(['auth:sanctum', 'active.account', 'school.context', 'password.changed', 'throttle:api'])->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

        Route::get('/dashboard', DashboardController::class)->name('dashboard');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
        Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
        Route::put('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');

        Route::post('/notifications/devices', [DevicePushTokenController::class, 'store'])->name('notifications.devices.store');
        Route::delete('/notifications/devices/{device}', [DevicePushTokenController::class, 'destroy'])
            ->whereNumber('device')
            ->name('notifications.devices.destroy');

        Route::get('/school', [SchoolController::class, 'show'])->name('school.show');
        Route::put('/school', [SchoolController::class, 'update'])->name('school.update');

        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::patch('/users/{user}/status', [UserController::class, 'toggleStatus'])->name('users.status');
        Route::post('/users/{user}/reset-link', [UserController::class, 'sendResetLink'])->name('users.reset-link');
    });
});
