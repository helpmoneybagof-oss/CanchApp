<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CourtController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\ReservationController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Reservas
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');
    Route::patch('/reservations/{reservation}/pay', [ReservationController::class, 'markAsPaid'])->name('reservations.pay');
    Route::patch('/reservations/{reservation}/approve-payment', [ReservationController::class, 'approvePayment'])->name('reservations.approve-payment');
    Route::patch('/reservations/{reservation}/reject-payment', [ReservationController::class, 'rejectPayment'])->name('reservations.reject-payment');
    Route::delete('/reservations/{reservation}/dismiss-payment', [ReservationController::class, 'dismissPayment'])->name('reservations.dismiss-payment');
    Route::get('/payments/pending', [ReservationController::class, 'pendingPayments'])->name('payments.pending');

    // Calendario admin
    Route::get('/calendar', [ReservationController::class, 'calendar'])->name('calendar');
    Route::get('/calendar/slots', [ReservationController::class, 'calendarSlots'])->name('calendar.slots');

    // Slots: bloquear/desbloquear
    Route::post('/slots/block', [ReservationController::class, 'blockSlots'])->name('slots.block');
    Route::post('/slots/unblock', [ReservationController::class, 'unblockSlots'])->name('slots.unblock');

    // Productos
    Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');
    Route::patch('/products/{product}/toggle', [ProductController::class, 'toggleActive'])->name('products.toggle');

    // Categorías
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    // Usuarios
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::patch('/users/{user}/toggle', [UserController::class, 'toggleActive'])->name('users.toggle');

    // Configuración
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/password', [SettingsController::class, 'changePassword'])->name('settings.password');

    // Reportes
    Route::get('/reports', [ReportController::class, 'index'])->name('reports');

    // Canchas
    Route::get('/courts', [CourtController::class, 'index'])->name('courts.index');
    Route::post('/courts', [CourtController::class, 'store'])->name('courts.store');
    Route::put('/courts/{court}', [CourtController::class, 'update'])->name('courts.update');
    Route::delete('/courts/{court}', [CourtController::class, 'destroy'])->name('courts.destroy');
    Route::patch('/courts/{court}/toggle', [CourtController::class, 'toggleActive'])->name('courts.toggle');
});
