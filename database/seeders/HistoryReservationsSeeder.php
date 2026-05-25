<?php

namespace Database\Seeders;

use App\Models\Court;
use App\Models\Reservation;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\TimeSlotService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Genera un historial de reservas a lo largo de mayo 2026 con fines
 * académicos / demostración del dashboard de reportes.
 *
 * Distribución de flujo según día de la semana:
 *   - Lun a Vie: 5-8 reservas/día (alto flujo: después de trabajo)
 *   - Sábado:    6-9 reservas/día (pico: día libre)
 *   - Domingo:   1-3 reservas/día (bajo: descanso)
 *
 * Ejecutar:
 *   php artisan db:seed --class=HistoryReservationsSeeder
 */
class HistoryReservationsSeeder extends Seeder
{
    public function run(): void
    {
        $slotService = app(TimeSlotService::class);

        $courts = Court::active()->get();
        if ($courts->isEmpty()) {
            $this->command->error('No hay canchas activas. Crea al menos una cancha antes de correr este seeder.');

            return;
        }

        // Crear/actualizar clientes demo variados
        $this->ensureDemoClients();

        // Eliminar el "Cliente Demo" original si existe (era placeholder del seeder principal)
        User::where('email', 'cliente@cancha.com')->delete();

        // Pool de clientes (excluye el placeholder por si quedó referenciado)
        $clients = User::where('role', 'client')
            ->where('active', true)
            ->where('email', '!=', 'cliente@cancha.com')
            ->get();

        if ($clients->isEmpty()) {
            $this->command->error('No hay clientes disponibles tras la limpieza.');

            return;
        }

        // Rango: del 1 al 25 de mayo 2026
        $start = Carbon::create(2026, 5, 1);
        $end   = Carbon::create(2026, 5, 25);

        $created = 0;
        $skipped = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();
            $reservasDelDia = $this->reservationsForWeekday($date->dayOfWeek);

            for ($i = 0; $i < $reservasDelDia; $i++) {
                $court = $courts->random();

                // Asegurar que existan los slots para esa cancha y fecha
                $slotService->generateSlotsForDate($dateStr, $court);

                // Slots disponibles de esa cancha+día que no estén reservados aún
                $available = TimeSlot::where('court_id', $court->id)
                    ->where('date', $dateStr)
                    ->where('status', 'available')
                    ->orderBy('start_time')
                    ->get();

                if ($available->isEmpty()) {
                    $skipped++;
                    continue;
                }

                // Duración: 1h (60%) o 2h (40%)
                $duration = rand(1, 100) <= 60 ? 1 : 2;
                if ($available->count() < $duration) {
                    $duration = 1;
                }

                // Preferencia por horarios pico (18:00 - 21:00) — busca slot dentro de ese rango
                $peak = $available->filter(fn ($s) => (int) substr($s->start_time, 0, 2) >= 18
                    && (int) substr($s->start_time, 0, 2) <= 21
                );
                $pool = $peak->isNotEmpty() && rand(0, 100) < 70 ? $peak->values() : $available;

                $startIdx = rand(0, max(0, $pool->count() - 1));
                $startSlot = $pool[$startIdx];

                // Tomar N slots consecutivos a partir del startSlot
                $startTime = $startSlot->start_time;
                $chosen = $available->filter(fn ($s) => $s->start_time >= $startTime)
                    ->sortBy('start_time')
                    ->take($duration)
                    ->values();

                // Validar continuidad
                $continuous = $chosen->count() === $duration;
                for ($k = 1; $k < $chosen->count(); $k++) {
                    if ($chosen[$k]->start_time !== $chosen[$k - 1]->end_time) {
                        $continuous = false;
                        break;
                    }
                }
                if (! $continuous) {
                    $chosen = collect([$startSlot]);
                }

                $courtPrice = $chosen->sum(fn ($s) => (float) $s->price);
                $client = $clients->random();
                [$status, $payment] = $this->pickStatusAndPayment();

                try {
                    DB::beginTransaction();

                    $reservation = Reservation::create([
                        'user_id'           => $client->id,
                        'court_id'          => $court->id,
                        'date'              => $dateStr,
                        'start_time'        => $chosen->first()->start_time,
                        'end_time'          => $chosen->last()->end_time,
                        'duration_hours'    => $chosen->count(),
                        'status'            => $status,
                        'payment_status'    => $payment,
                        'court_price'       => $courtPrice,
                        'consumables_price' => 0,
                        'total_price'       => $courtPrice,
                        'notes'             => $this->randomNote(),
                        'created_at'        => $date->copy()->subDays(rand(0, 7))->setTime(rand(8, 22), rand(0, 59)),
                    ]);

                    $reservation->timeSlots()->attach($chosen->pluck('id')->toArray());

                    // Marcar slots según el estado del pago, salvo cancelled
                    if ($status !== 'cancelled') {
                        $slotStatus = $payment === 'paid' ? 'reserved' : 'pre_reserved';
                        TimeSlot::whereIn('id', $chosen->pluck('id'))->update(['status' => $slotStatus]);
                    }

                    DB::commit();
                    $created++;
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->command->warn("Error en {$dateStr}: ".$e->getMessage());
                    $skipped++;
                }
            }
        }

        $this->command->info("✅ Reservas creadas: {$created}");
        if ($skipped > 0) {
            $this->command->warn("⚠️  Saltadas (sin slots libres o error): {$skipped}");
        }
    }

    /**
     * Reservas por día según día de la semana.
     *  - Domingo: muy bajo (1-3)
     *  - Lun-Vie: alto (5-8)
     *  - Sábado: pico (6-9)
     */
    private function reservationsForWeekday(int $dayOfWeek): int
    {
        return match ($dayOfWeek) {
            Carbon::SUNDAY    => rand(1, 3),
            Carbon::SATURDAY  => rand(6, 9),
            default           => rand(5, 8),
        };
    }

    /**
     * Distribución realista de estados:
     *  - 62% confirmed + paid
     *  - 16% completed + paid (partidos ya jugados)
     *  - 10% confirmed + unpaid (pago pendiente)
     *  -  7% confirmed + payment_review (comprobante enviado)
     *  -  5% cancelled + unpaid
     */
    private function pickStatusAndPayment(): array
    {
        $r = rand(1, 100);
        if ($r <= 62) {
            return ['confirmed', 'paid'];
        }
        if ($r <= 78) {
            return ['completed', 'paid'];
        }
        if ($r <= 88) {
            return ['confirmed', 'unpaid'];
        }
        if ($r <= 95) {
            return ['confirmed', 'payment_review'];
        }

        return ['cancelled', 'unpaid'];
    }

    private function randomNote(): ?string
    {
        $notes = [
            null, null, null, null, null, null, null, // 70% sin notas
            'Somos 10 personas, llevaremos petos',
            'Cumpleaños — necesitamos extra de bebidas',
            'Partido de despedida del equipo',
            'Primera vez en la cancha',
            'Llegamos 5 min tarde por el tráfico',
            'Por favor tener listo el balón',
            'Vamos con el equipo de la oficina',
            'Reservamos para los pelados del barrio',
            'Quincenal del equipo, plis cancha limpia',
        ];

        return $notes[array_rand($notes)];
    }

    /**
     * Crea/actualiza un conjunto variado de clientes demo con nombres
     * típicos colombianos para que el dashboard se vea real.
     */
    private function ensureDemoClients(): void
    {
        $demos = [
            ['Juan Restrepo',          'juan.restrepo@demo.com',     '3001112233'],
            ['Andrés Gómez',           'andres.gomez@demo.com',      '3002223344'],
            ['María Fernanda Ruiz',    'maria.ruiz@demo.com',        '3003334455'],
            ['Camilo Vargas',          'camilo.vargas@demo.com',     '3004445566'],
            ['Laura Castillo',         'laura.castillo@demo.com',    '3005556677'],
            ['Sebastián López',        'sebas.lopez@demo.com',       '3006667788'],
            ['Valentina Torres',       'valen.torres@demo.com',      '3007778899'],
            ['Mateo Rodríguez',        'mateo.rodriguez@demo.com',   '3008889900'],
            ['Daniela Mejía',          'dani.mejia@demo.com',        '3009990011'],
            ['Santiago Quintero',      'santi.quintero@demo.com',    '3010001122'],
            ['Isabella Pardo',         'isabella.pardo@demo.com',    '3011112233'],
            ['Nicolás Henao',          'nico.henao@demo.com',        '3012223344'],
            ['Catalina Salazar',       'cata.salazar@demo.com',      '3013334455'],
            ['Felipe Cárdenas',        'felipe.cardenas@demo.com',   '3014445566'],
        ];

        foreach ($demos as [$name, $email, $phone]) {
            User::updateOrCreate(['email' => $email], [
                'name'              => $name,
                'phone'             => $phone,
                'password'          => bcrypt('Demo2026*'),
                'role'              => 'client',
                'active'            => true,
                'email_verified_at' => now(),
            ]);
        }
    }
}
