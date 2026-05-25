<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReservationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Reservation $reservation,
        public readonly string $label = '1 hora'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $courtName = $this->reservation->court?->name ?? 'Cancha';

        return [
            'type' => 'reservation_reminder',
            'title' => '⏰ Recordatorio de reserva',
            'body' => "Tu partido en {$courtName} empieza en {$this->label}.",
            'icon' => '⏰',
            'url' => "/reservations/{$this->reservation->id}",
        ];
    }
}
