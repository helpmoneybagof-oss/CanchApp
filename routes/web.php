<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        if ($user->isAdmin()) {
            return redirect('/admin/dashboard');
        }
        return inertia('Dashboard');
    })->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/client.php';

// API: Push subscriptions + Notifications (requiere auth)
Route::middleware(['auth'])->prefix('api')->group(function () {
    Route::post('/push/subscribe',   [\App\Http\Controllers\Api\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [\App\Http\Controllers\Api\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    // Notificaciones en campana
    Route::get('/notifications',              [\App\Http\Controllers\Api\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all',    [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read',   [\App\Http\Controllers\Api\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('/notifications/{id}',      [\App\Http\Controllers\Api\NotificationController::class, 'destroy'])->name('notifications.destroy');
});
