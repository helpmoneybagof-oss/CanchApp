<?php

namespace App\Notifications;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PaymentProofSubmittedNotification extends Notification implements ShouldQueue
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
            'type' => 'payment_proof_submitted',
            'title' => '💳 Nuevo comprobante',
            'body' => "{$this->reservation->user->name} subió el comprobante de la reserva #{$this->reservation->confirmation_code}.",
            'icon' => '💳',
            'url' => '/admin/payments/pending',
        ];
    }
}
