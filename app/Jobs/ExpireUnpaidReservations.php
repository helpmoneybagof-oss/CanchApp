<?php

namespace App\Jobs;

use App\Models\Reservation;
use App\Notifications\ReservationCancelledNotification;
use App\Services\NotificationService;
use App\Services\PushNotificationService;
use App\Services\TimeSlotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ExpireUnpaidReservations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TimeSlotService $slotService, NotificationService $notif, PushNotificationService $push): void
    {
        // Buscar reservas con payment_expires_at vencido y sin pago confirmado
        $expired = Reservation::with('user')
            ->whereIn('payment_status', ['unpaid', 'pending_payment'])
            ->whereNotNull('payment_expires_at')
            ->where('payment_expires_at', '<=', now())
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->get();

        foreach ($expired as $reservation) {
            try {
                DB::beginTransaction();

                $slotIds = $reservation->timeSlots()->pluck('time_slots.id')->toArray();

                // Cancelar primero, luego liberar slots
                $reservation->update([
                    'status'              => 'cancelled',
                    'cancellation_reason' => 'Reserva expirada por falta de pago',
                    'payment_status'      => 'unpaid',
                ]);

                $slotService->releaseSlots($slotIds);

                DB::commit();

                Log::info("Reserva #{$reservation->id} expirada por falta de pago.");

                // Notificar al cliente que expiró (DB + Push)
                if ($reservation->user) {
                    try {
                        $notif->notifyUser($reservation->user, new ReservationCancelledNotification($reservation));
                        $push->sendToUser(
                            userId: $reservation->user_id,
                            title:  '⏰ Reserva expirada',
                            body:   "Tu reserva #{$reservation->confirmation_code} expiró por falta de pago y fue cancelada automáticamente.",
                            data:   ['url' => '/reservations'],
                        );
                    } catch (\Throwable $e) {
                        Log::warning("Notif/Push cliente error (expiración reserva #{$reservation->id}): {$e->getMessage()}");
                    }
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                Log::error("Error al expirar reserva #{$reservation->id}: {$e->getMessage()}");
            }
        }
    }
}
