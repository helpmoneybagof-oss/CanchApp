<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Notifications\ReservationReminderNotification;
use App\Services\NotificationService;
use App\Services\PushNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendReservationPushReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public Reservation $reservation,
        public string $label = '1 hora'
    ) {}

    public function handle(NotificationService $notif, PushNotificationService $push): void
    {
        $this->reservation->load(['court', 'user']);

        if (! $this->reservation->user) {
            return;
        }

        try {
            $notif->notifyUser($this->reservation->user, new ReservationReminderNotification($this->reservation, $this->label));
            $push->sendToUser(
                userId: $this->reservation->user_id,
                title: '⏰ Recordatorio de reserva',
                body: "Tu partido empieza en {$this->label}.",
                data: ['url' => "/reservations/{$this->reservation->id}"],
            );
        } catch (\Throwable $e) {
            Log::warning("Push reminder error (reserva #{$this->reservation->id}): {$e->getMessage()}");
        }
    }
}
