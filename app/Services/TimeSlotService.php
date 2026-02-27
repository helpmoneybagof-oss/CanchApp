<?php

namespace App\Services;

use App\Models\Court;
use App\Models\TimeSlot;
use Carbon\CarbonPeriod;

class TimeSlotService
{
    /**
     * Genera los slots para una cancha y un día si no existen aún.
     */
    public function generateSlotsForDate(string $date, Court $court): void
    {
        $existing = TimeSlot::forDate($date)->forCourt($court->id)->count();

        if ($existing > 0) {
            return;
        }

        $slots = [];
        for ($hour = $court->start_hour; $hour < $court->end_hour; $hour++) {
            $slots[] = [
                'court_id'   => $court->id,
                'date'       => $date,
                'start_time' => sprintf('%02d:00:00', $hour),
                'end_time'   => sprintf('%02d:00:00', $hour + 1),
                'status'     => 'available',
                'price'      => (float) $court->price_per_hour,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        TimeSlot::insert($slots);
    }

    /**
     * Genera slots para un rango de fechas para una cancha.
     */
    public function generateSlotsForDateRange(string $from, string $to, Court $court): void
    {
        $period = CarbonPeriod::create($from, $to);
        foreach ($period as $date) {
            $this->generateSlotsForDate($date->toDateString(), $court);
        }
    }

    /**
     * Retorna los slots de un día para una cancha, listos para el calendario.
     */
    public function getSlotsForDate(string $date, Court $court): array
    {
        $this->generateSlotsForDate($date, $court);

        $slots = TimeSlot::forDate($date)
            ->forCourt($court->id)
            ->orderBy('start_time')
            ->get();

        // Contar pre-reservas activas por slot en una sola query
        $preReservedCounts = $this->getPreReservedCounts($slots->pluck('id')->toArray());

        return $slots->map(fn (TimeSlot $slot) => [
                'id'                  => $slot->id,
                'court_id'            => $slot->court_id,
                'date'                => $slot->date->format('Y-m-d'),
                'start_time'          => $slot->start_time,
                'end_time'            => $slot->end_time,
                'start_formatted'     => $slot->start_time_formatted,
                'end_formatted'       => $slot->end_time_formatted,
                'status'              => $slot->status,
                'price'               => (float) $slot->price,
                'block_reason'        => $slot->block_reason,
                'pre_reserved_count'  => $preReservedCounts[$slot->id] ?? 0,
            ])
            ->toArray();
    }

    /**
     * Retorna slots de un rango de fechas para una cancha.
     */
    public function getSlotsForDateRange(string $from, string $to, Court $court): array
    {
        $this->generateSlotsForDateRange($from, $to, $court);

        $slots = TimeSlot::forDateRange($from, $to)
            ->forCourt($court->id)
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $slotIds = $slots->pluck('id')->toArray();
        $preReservedCounts = $this->getPreReservedCounts($slotIds);
        $reservationIds    = $this->getReservationIds($slotIds);

        $nowBogota = \Carbon\Carbon::now('America/Bogota');

        return $slots->map(fn (TimeSlot $slot) => [
                'id'                  => $slot->id,
                'court_id'            => $slot->court_id,
                'date'                => $slot->date->format('Y-m-d'),
                'start_time'          => $slot->start_time,
                'end_time'            => $slot->end_time,
                'start_formatted'     => $slot->start_time_formatted,
                'end_formatted'       => $slot->end_time_formatted,
                'status'              => $slot->status,
                'price'               => (float) $slot->price,
                'block_reason'        => $slot->block_reason,
                'pre_reserved_count'  => $preReservedCounts[$slot->id] ?? 0,
                'reservation_id'      => $reservationIds[$slot->id] ?? null,
                'is_finished'         => $slot->status === 'reserved'
                                         && \Carbon\Carbon::parse(
                                                $slot->date->format('Y-m-d') . ' ' . $slot->end_time,
                                                'America/Bogota'
                                            )->lt($nowBogota),
            ])
            ->toArray();
    }

    /**
     * Verifica si un conjunto de slots están disponibles para reservar
     * (available o pre_reserved — ambos son reservables).
     */
    public function areSlotsAvailable(array $slotIds): bool
    {
        $count = TimeSlot::whereIn('id', $slotIds)
            ->whereIn('status', ['available', 'pre_reserved'])
            ->count();

        return $count === count($slotIds);
    }

    /**
     * Marca slots como pre_reserved (reserva sin pago confirmado).
     * El slot sigue visible y reservable por otros clientes.
     */
    public function markAsPreReserved(array $slotIds): void
    {
        TimeSlot::whereIn('id', $slotIds)
            ->whereIn('status', ['available', 'pre_reserved'])
            ->update(['status' => 'pre_reserved']);

        $this->broadcastSlotChanges($slotIds);
    }

    /**
     * Marca slots como reservados definitivamente (pago confirmado).
     * Cancela automáticamente todas las otras pre-reservas del mismo slot.
     */
    public function markAsReserved(array $slotIds, int $winnerReservationId): void
    {
        TimeSlot::whereIn('id', $slotIds)->update(['status' => 'reserved']);

        // Cancelar otras reservas pre-pagadas que tenían estos mismos slots
        $otherReservationIds = \DB::table('reservation_time_slots')
            ->whereIn('time_slot_id', $slotIds)
            ->where('reservation_id', '!=', $winnerReservationId)
            ->pluck('reservation_id')
            ->unique()
            ->toArray();

        if (! empty($otherReservationIds)) {
            \App\Models\Reservation::whereIn('id', $otherReservationIds)
                ->whereIn('payment_status', ['unpaid', 'pending_payment', 'payment_review', 'rejected'])
                ->update([
                    'status'              => 'cancelled',
                    'cancellation_reason' => 'El horario fue reservado por otro usuario que completó el pago primero.',
                ]);
        }

        $this->broadcastSlotChanges($slotIds);
    }

    /**
     * Libera slots (los vuelve a disponible).
     * Solo los libera si no hay otras reservas activas (pre_reserved) sobre ellos.
     */
    public function releaseSlots(array $slotIds): void
    {
        foreach ($slotIds as $slotId) {
            // Contar reservas ACTIVAS (no canceladas, no pagadas) que aún tienen este slot
            $activeCount = \DB::table('reservation_time_slots')
                ->join('reservations', 'reservations.id', '=', 'reservation_time_slots.reservation_id')
                ->where('reservation_time_slots.time_slot_id', $slotId)
                ->whereNotIn('reservations.status', ['cancelled', 'completed'])
                ->whereIn('reservations.payment_status', ['unpaid', 'pending_payment', 'payment_review'])
                ->count();

            if ($activeCount > 0) {
                // Aún hay pre-reservas activas → mantener pre_reserved
                TimeSlot::where('id', $slotId)
                    ->where('status', '!=', 'reserved')
                    ->update(['status' => 'pre_reserved']);
            } else {
                // No quedan pre-reservas → liberar completamente
                TimeSlot::where('id', $slotId)
                    ->where('status', '!=', 'reserved')
                    ->update(['status' => 'available']);
            }
        }

        $this->broadcastSlotChanges($slotIds);
    }

    /**
     * Emite el evento SlotStatusChanged para todos los slots afectados,
     * agrupados por cancha y fecha para minimizar el número de broadcasts.
     */
    private function broadcastSlotChanges(array $slotIds): void
    {
        if (empty($slotIds)) return;

        $slots = TimeSlot::whereIn('id', $slotIds)
            ->select('court_id', 'date')
            ->distinct()
            ->get();

        foreach ($slots as $slot) {
            broadcast(new \App\Events\SlotStatusChanged(
                $slot->court_id,
                $slot->date->format('Y-m-d'),
            ));
        }
    }

    /**
     * Retorna el reservation_id más reciente (pagado primero, luego pre-reservado) por slot_id.
     * Útil para el calendario admin para navegar al detalle de la reserva.
     */
    private function getReservationIds(array $slotIds): array
    {
        if (empty($slotIds)) return [];

        $rows = \DB::table('reservation_time_slots')
            ->join('reservations', 'reservations.id', '=', 'reservation_time_slots.reservation_id')
            ->whereIn('reservation_time_slots.time_slot_id', $slotIds)
            ->whereNotIn('reservations.status', ['cancelled'])
            ->orderByRaw("FIELD(reservations.payment_status, 'paid', 'payment_review', 'pending_payment', 'unpaid')")
            ->select('reservation_time_slots.time_slot_id', 'reservations.id as reservation_id')
            ->get();

        // Tomar la primera (más prioritaria) por slot
        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row->time_slot_id])) {
                $result[$row->time_slot_id] = $row->reservation_id;
            }
        }
        return $result;
    }

    /**
     * Retorna el conteo de pre-reservas activas por slot_id.
     */
    private function getPreReservedCounts(array $slotIds): array
    {
        if (empty($slotIds)) return [];

        $rows = \DB::table('reservation_time_slots')
            ->join('reservations', 'reservations.id', '=', 'reservation_time_slots.reservation_id')
            ->whereIn('reservation_time_slots.time_slot_id', $slotIds)
            ->whereNotIn('reservations.status', ['cancelled'])
            ->whereIn('reservations.payment_status', ['unpaid', 'pending_payment', 'payment_review'])
            ->select('reservation_time_slots.time_slot_id', \DB::raw('COUNT(*) as count'))
            ->groupBy('reservation_time_slots.time_slot_id')
            ->get();

        return $rows->pluck('count', 'time_slot_id')->toArray();
    }

    /**
     * Bloquea slots por el admin.
     */
    public function blockSlots(array $slotIds, string $reason = ''): void
    {
        TimeSlot::whereIn('id', $slotIds)->update([
            'status'       => 'blocked',
            'block_reason' => $reason,
        ]);
    }
}
