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
 * Historial de reservas de ABRIL 2026 con volumen ligeramente menor al
 * de mayo, para que el dashboard muestre crecimiento mes a mes.
 *
 * Distribución de flujo según día de la semana (≈20% menos que mayo):
 *   - Lun a Vie: 4-6 reservas/día (vs 5-8 en mayo)
 *   - Sábado:    4-7 reservas/día (vs 6-9 en mayo)
 *   - Domingo:   1-2 reservas/día (vs 1-3 en mayo)
 *
 * Ejecutar:
 *   php artisan db:seed --class=HistoryReservationsAprilSeeder
 *
 * Recomendación: correr este PRIMERO, luego el de mayo.
 */
class HistoryReservationsAprilSeeder extends Seeder
{
    public function run(): void
    {
        $slotService = app(TimeSlotService::class);

        $courts = Court::active()->get();
        if ($courts->isEmpty()) {
            $this->command->error('No hay canchas activas. Crea al menos una cancha antes de correr este seeder.');

            return;
        }

        // Reutilizamos los mismos clientes demo que crea el seeder de mayo
        $this->ensureDemoClients();
        User::where('email', 'cliente@cancha.com')->delete();

        $clients = User::where('role', 'client')
            ->where('active', true)
            ->where('email', '!=', 'cliente@cancha.com')
            ->get();

        if ($clients->isEmpty()) {
            $this->command->error('No hay clientes disponibles tras la limpieza.');

            return;
        }

        // Rango: del 1 al 30 de abril 2026
        $start = Carbon::create(2026, 4, 1);
        $end   = Carbon::create(2026, 4, 30);

        $created = 0;
        $skipped = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();
            $reservasDelDia = $this->reservationsForWeekday($date->dayOfWeek);

            for ($i = 0; $i < $reservasDelDia; $i++) {
                $court = $courts->random();

                $slotService->generateSlotsForDate($dateStr, $court);

                $available = TimeSlot::where('court_id', $court->id)
                    ->where('date', $dateStr)
                    ->where('status', 'available')
                    ->orderBy('start_time')
                    ->get();

                if ($available->isEmpty()) {
                    $skipped++;
                    continue;
                }

                $duration = rand(1, 100) <= 60 ? 1 : 2;
                if ($available->count() < $duration) {
                    $duration = 1;
                }

                // Preferencia por horarios pico (18:00-21:00)
                $peak = $available->filter(fn ($s) => (int) substr($s->start_time, 0, 2) >= 18
                    && (int) substr($s->start_time, 0, 2) <= 21
                );
                $pool = $peak->isNotEmpty() && rand(0, 100) < 70 ? $peak->values() : $available;

                $startIdx = rand(0, max(0, $pool->count() - 1));
                $startSlot = $pool[$startIdx];
                $startTime = $startSlot->start_time;

                $chosen = $available->filter(fn ($s) => $s->start_time >= $startTime)
                    ->sortBy('start_time')
                    ->take($duration)
                    ->values();

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

                // Todas las reservas de abril son pasadas (hoy estamos en mayo).
                // 85% jugó y pagó, 15% se cayó sin pagar.
                $r = rand(1, 100);
                [$status, $payment] = $r <= 85
                    ? ['completed', 'paid']
                    : ['cancelled', 'unpaid'];

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

                    if ($status !== 'cancelled') {
                        TimeSlot::whereIn('id', $chosen->pluck('id'))->update(['status' => 'reserved']);
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

        $this->command->info("✅ Reservas de abril creadas: {$created}");
        if ($skipped > 0) {
            $this->command->warn("⚠️  Saltadas: {$skipped}");
        }
    }

    /**
     * Volumen ~20% menor que mayo para mostrar crecimiento.
     */
    private function reservationsForWeekday(int $dayOfWeek): int
    {
        return match ($dayOfWeek) {
            Carbon::SUNDAY    => rand(1, 2),
            Carbon::SATURDAY  => rand(4, 7),
            default           => rand(4, 6),
        };
    }

    private function randomNote(): ?string
    {
        $notes = [
            null, null, null, null, null, null, null,
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
     * Mismo set de clientes que el seeder de mayo, idempotente.
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
