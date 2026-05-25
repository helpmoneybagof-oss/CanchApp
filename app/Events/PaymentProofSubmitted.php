<?php

namespace App\Events;

use App\Models\Reservation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class PaymentProofSubmitted implements ShouldBroadcast
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
        return 'payment.proof_submitted';
    }

    public function broadcastWith(): array
    {
        $this->reservation->load('user');

        return [
            'id' => $this->reservation->id,
            'confirmation_code' => $this->reservation->confirmation_code,
            'date' => $this->reservation->date_formatted,
            'start_time' => $this->reservation->start_time_formatted,
            'end_time' => $this->reservation->end_time_formatted,
            'total_price' => (float) $this->reservation->total_price,
            'payment_method' => $this->reservation->payment_method,
            'payment_reference' => $this->reservation->payment_reference,
            'payment_proof_url' => $this->reservation->payment_proof
                ? Storage::url($this->reservation->payment_proof)
                : null,
            'submitted_at' => $this->reservation->updated_at->format('d/m/Y H:i'),
            'user' => [
                'name' => $this->reservation->user?->name,
                'email' => $this->reservation->user?->email,
                'phone' => $this->reservation->user?->phone,
            ],
        ];
    }
}
