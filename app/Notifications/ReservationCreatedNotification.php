<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReservationCreatedNotification extends Notification implements ShouldQueue
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
            'type'  => 'reservation_created',
            'title' => '📅 Nueva reserva',
            'body'  => "{$this->reservation->user->name} reservó el {$this->reservation->date_formatted} a las {$this->reservation->start_time_formatted}.",
            'icon'  => '📅',
            'url'   => "/admin/reservations/{$this->reservation->id}",
        ];
    }
}
