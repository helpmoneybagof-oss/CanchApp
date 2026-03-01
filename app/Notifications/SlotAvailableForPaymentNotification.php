<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class SlotAvailableForPaymentNotification extends Notification implements ShouldQueue
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
            'type'  => 'slot_available_for_payment',
            'title' => '💳 ¡Hora de pagar!',
            'body'  => "El horario de tu pre-reserva #{$this->reservation->confirmation_code} está disponible. ¡Sube tu comprobante antes que otro usuario!",
            'icon'  => '💳',
            'url'   => "/reservations/{$this->reservation->id}/payment",
        ];
    }
}
