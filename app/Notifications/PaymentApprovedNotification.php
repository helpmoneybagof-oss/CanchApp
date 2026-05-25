<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentApprovedNotification extends Notification implements ShouldQueue
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
            'type'  => 'payment_approved',
            'title' => '✅ Pago aprobado',
            'body'  => "Tu reserva #{$this->reservation->confirmation_code} fue confirmada. ¡Nos vemos en la cancha!",
            'icon'  => '✅',
            'url'   => "/reservations/{$this->reservation->id}",
        ];
    }
}
