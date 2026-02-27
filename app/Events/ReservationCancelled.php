<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCancelled implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin'),
            new PrivateChannel('user.' . $this->reservation->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reservation.cancelled';
    }

    public function broadcastWith(): array
    {
        return [
            'id'             => $this->reservation->id,
            'confirmation_code' => $this->reservation->confirmation_code,
            'user_id'        => $this->reservation->user_id,
        ];
    }
}
