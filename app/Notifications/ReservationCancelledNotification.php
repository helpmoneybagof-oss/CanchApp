<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReservationCancelledNotification extends Notification implements ShouldQueue
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
            'type'  => 'reservation_cancelled',
            'title' => '❌ Reserva cancelada',
            'body'  => "Tu reserva #{$this->reservation->confirmation_code} fue cancelada por el administrador.",
            'icon'  => '❌',
            'url'   => '/reservations',
        ];
    }
}
