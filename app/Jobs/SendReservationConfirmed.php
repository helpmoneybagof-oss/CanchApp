<?php

namespace App\Jobs;

use App\Mail\NewReservationAdmin;
use App\Mail\ReservationConfirmed;
use App\Models\Reservation;
use App\Models\Setting;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendReservationConfirmed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Reservation $reservation) {}

    public function handle(PushNotificationService $push): void
    {
        $this->reservation->load(['court', 'items.product', 'user']);

        // Correo al cliente
        Mail::to($this->reservation->user->email)
            ->send(new ReservationConfirmed($this->reservation));

        // Pequeña pausa para respetar rate limits en entornos de prueba
        sleep(2);

        // Correo al admin (si tiene email configurado)
        $adminEmail = Setting::getValue('contact_email');
        if ($adminEmail) {
            Mail::to($adminEmail)
                ->send(new NewReservationAdmin($this->reservation));
        }

        // Push al admin: nueva reserva (con URL al detalle de la reserva)
        try {
            $courtName = $this->reservation->court?->name ?? 'Cancha';
            $push->sendToAdmins(
                title: '📅 Nueva reserva',
                body: "{$this->reservation->user->name} reservó {$courtName} el {$this->reservation->date_formatted}",
                data: ['url' => "/admin/reservations/{$this->reservation->id}"],
            );
        } catch (\Throwable $e) {
            Log::warning("Push admin error (reserva #{$this->reservation->id}): {$e->getMessage()}");
        }
    }
}
