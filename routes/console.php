<?php

use App\Jobs\ExpireUnpaidReservations;
use App\Jobs\SendDailySummary;
use App\Jobs\SendLowStockAlert;
use App\Jobs\SendReservationReminder;
use App\Models\Reservation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Recordatorios de reservas ────────────────────────────────────────────────
// Se ejecuta cada hora y busca reservas que estén a 24h o 2h de empezar.
Schedule::call(function () {
    $now = now();

    // Recordatorio 24 horas antes
    $target24h = $now->copy()->addHours(24);
    $reservations24h = Reservation::with(['user', 'court'])
        ->where('status', 'confirmed')
        ->whereDate('date', $target24h->toDateString())
        ->whereTime('start_time', '>=', $target24h->format('H:00:00'))
        ->whereTime('start_time', '<',  $target24h->copy()->addHour()->format('H:00:00'))
        ->get();

    foreach ($reservations24h as $reservation) {
        SendReservationReminder::dispatch($reservation, '24 horas');
    }

    // Recordatorio 2 horas antes (email)
    $target2h = $now->copy()->addHours(2);
    $reservations2h = Reservation::with(['user', 'court'])
        ->where('status', 'confirmed')
        ->whereDate('date', $target2h->toDateString())
        ->whereTime('start_time', '>=', $target2h->format('H:00:00'))
        ->whereTime('start_time', '<',  $target2h->copy()->addHour()->format('H:00:00'))
        ->get();

    foreach ($reservations2h as $reservation) {
        SendReservationReminder::dispatch($reservation, '2 horas');
    }

    // Recordatorio 1 hora antes (Push + campana)
    $target1h = $now->copy()->addHours(1);
    $reservations1h = Reservation::with(['user', 'court'])
        ->where('status', 'confirmed')
        ->whereDate('date', $target1h->toDateString())
        ->whereTime('start_time', '>=', $target1h->format('H:00:00'))
        ->whereTime('start_time', '<',  $target1h->copy()->addHour()->format('H:00:00'))
        ->get();

    foreach ($reservations1h as $reservation) {
        \App\Jobs\SendReservationPushReminder::dispatch($reservation, '1 hora');
    }
})->hourly()->name('send-reservation-reminders');

// ─── Resumen diario al admin (cada mañana a las 7:00 AM) ─────────────────────
Schedule::job(new SendDailySummary())->dailyAt('07:00')->name('send-daily-summary');

// ─── Alerta de stock bajo (cada día a las 8:00 AM) ───────────────────────────
Schedule::job(new SendLowStockAlert())->dailyAt('08:00')->name('send-low-stock-alert');

// ─── Expirar reservas sin pago (cada 5 minutos) ───────────────────────────────
Schedule::job(new ExpireUnpaidReservations())->everyFiveMinutes()->name('expire-unpaid-reservations');

