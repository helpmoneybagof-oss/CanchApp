<?php

namespace App\Jobs;

use App\Mail\ReservationReminder;
use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendReservationReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Reservation $reservation,
        public string $hoursLabel,
    ) {}

    public function handle(): void
    {
        $this->reservation->load(['court', 'user']);

        Mail::to($this->reservation->user->email)
            ->send(new ReservationReminder($this->reservation, $this->hoursLabel));
    }
}
