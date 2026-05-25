<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PreReservationCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Reservation $reservation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'pre_reservation_cancelled',
            'title' => '⏳ Pre-reserva liberada',
            'body' => 'Otro usuario completó el pago primero y se liberó tu pre-reserva. Puedes intentar reservar otro horario.',
            'icon' => '⏳',
            'url' => '/reservations',
        ];
    }
}
