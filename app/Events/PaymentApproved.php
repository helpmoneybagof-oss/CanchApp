<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentApproved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->reservation->user_id),
            new PrivateChannel('admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'payment.approved';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->reservation->id,
            'confirmation_code' => $this->reservation->confirmation_code,
            'user_id' => $this->reservation->user_id,
            'payment_status' => $this->reservation->payment_status,
            'status' => $this->reservation->status,
        ];
    }
}
