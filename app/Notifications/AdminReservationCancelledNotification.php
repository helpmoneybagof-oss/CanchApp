<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AdminReservationCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Reservation $reservation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $userName = $this->reservation->user?->name ?? 'Un cliente';

        return [
            'type' => 'reservation_cancelled',
            'title' => '❌ Reserva cancelada',
            'body' => "{$userName} canceló la reserva #{$this->reservation->confirmation_code}.",
            'icon' => '❌',
            'url' => "/admin/reservations/{$this->reservation->id}",
        ];
    }
}
