<?php

namespace App\Jobs;

use App\Mail\CancellationAdmin;
use App\Mail\ReservationCancelled;
use App\Models\Reservation;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendReservationCancelled implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Reservation $reservation) {}

    public function handle(): void
    {
        $this->reservation->load(['court', 'user']);

        // Correo al cliente
        Mail::to($this->reservation->user->email)
            ->send(new ReservationCancelled($this->reservation));

        // Pequeña pausa para respetar rate limits en entornos de prueba
        sleep(2);

        // Correo al admin
        $adminEmail = Setting::getValue('contact_email');
        if ($adminEmail) {
            Mail::to($adminEmail)
                ->send(new CancellationAdmin($this->reservation));
        }
    }
}
