<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentRejectedNotification extends Notification implements ShouldQueue
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
            'type'  => 'payment_rejected',
            'title' => '❌ Comprobante rechazado',
            'body'  => "Tu comprobante de la reserva #{$this->reservation->confirmation_code} fue rechazado. Sube uno nuevo.",
            'icon'  => '❌',
            'url'   => "/reservations/{$this->reservation->id}",
        ];
    }
}
