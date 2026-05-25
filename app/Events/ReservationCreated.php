<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReservationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'reservation.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->reservation->id,
            'confirmation_code' => $this->reservation->confirmation_code,
            'user_name' => $this->reservation->user?->name ?? '',
            'date' => $this->reservation->date_formatted,
            'start_time' => $this->reservation->start_time_formatted,
            'end_time' => $this->reservation->end_time_formatted,
            'total_price' => (float) $this->reservation->total_price,
            'status' => $this->reservation->status,
            'payment_status' => $this->reservation->payment_status,
        ];
    }
}
