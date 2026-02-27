<?php

use App\Http\Controllers\Client\CalendarController;
use App\Http\Controllers\Client\CartController;
use App\Http\Controllers\Client\CatalogController;
use App\Http\Controllers\Client\PaymentController;
use App\Http\Controllers\Client\ReservationController;
use Illuminate\Support\Facades\Route;

// Calendario público (sin auth)
Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');

// API de slots: accesible sin auth pero con throttle para evitar abuso
Route::middleware(['throttle:60,1'])->group(function () {
    Route::get('/api/slots', [CalendarController::class, 'slots'])->name('slots.range');
    Route::get('/api/slots/day', [CalendarController::class, 'slotsForDay'])->name('slots.day');
});

// Catálogo público
Route::get('/catalog', [CatalogController::class, 'index'])->name('catalog');

// Rutas protegidas del cliente
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/reservations', [ReservationController::class, 'index'])->name('reservations.index');
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show'])->name('reservations.show');
    Route::get('/history', [ReservationController::class, 'history'])->name('reservations.history');
    Route::get('/checkout', [ReservationController::class, 'checkout'])->name('checkout');
    Route::post('/reservations', [ReservationController::class, 'store'])->name('reservations.store');
    Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->name('reservations.cancel');

    // Pagos
    Route::get('/reservations/{reservation}/payment', [PaymentController::class, 'show'])->name('reservations.payment');
    Route::post('/reservations/{reservation}/payment/proof', [PaymentController::class, 'uploadProof'])->name('reservations.payment.proof');

    // Carrito
    Route::get('/cart', [CartController::class, 'index'])->name('cart');
    Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.add');
    Route::delete('/cart/items/{productId}', [CartController::class, 'removeItem'])->name('cart.remove');
    Route::post('/cart/slots', [CartController::class, 'setSlots'])->name('cart.slots');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
});
