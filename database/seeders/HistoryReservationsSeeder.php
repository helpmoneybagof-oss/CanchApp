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

        $clients = User::where('role', 'client')->where('active', true)->get();
        if ($clients->count() < 3) {
            $this->command->warn('Hay menos de 3 clientes registrados. Creando clientes de prueba...');
            $this->createDemoClients();
            $clients = User::where('role', 'client')->where('active', true)->get();
        }

        // Rango: del 1 al 25 de mayo 2026 (no incluir hoy ni futuro)
        $start = Carbon::create(2026, 5, 1);
        $end   = Carbon::create(2026, 5, 25);

        $created = 0;
        $skipped = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateStr = $date->toDateString();
            $weekday = $date->dayOfWeek;

            // Fines de semana: 3-6 reservas. Entre semana: 1-4 reservas.
            $reservasDelDia = $this->isWeekend($weekday)
                ? rand(3, 6)
                : rand(1, 4);

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

                // Tomar slots consecutivos
                $startIdx = rand(0, max(0, $available->count() - $duration));
                $chosen = $available->slice($startIdx, $duration)->values();

                // Validar continuidad
                $continuous = true;
                for ($k = 1; $k < $chosen->count(); $k++) {
                    if ($chosen[$k]->start_time !== $chosen[$k - 1]->end_time) {
                        $continuous = false;
                        break;
                    }
                }
                if (! $continuous) {
                    $chosen = collect([$available->first()]);
                }

                $courtPrice = $chosen->sum(fn ($s) => (float) $s->price);
                $client = $clients->random();
                [$status, $payment] = $this->pickStatusAndPayment();

                try {
                    DB::beginTransaction();

                    $reservation = Reservation::create([
                        'user_id'          => $client->id,
                        'court_id'         => $court->id,
                        'date'             => $dateStr,
                        'start_time'       => $chosen->first()->start_time,
                        'end_time'         => $chosen->last()->end_time,
                        'duration_hours'   => $chosen->count(),
                        'status'           => $status,
                        'payment_status'   => $payment,
                        'court_price'      => $courtPrice,
                        'consumables_price' => 0,
                        'total_price'      => $courtPrice,
                        'notes'            => $this->randomNote(),
                        'created_at'       => $date->copy()->subDays(rand(0, 7))->setTime(rand(8, 22), rand(0, 59)),
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

    private function isWeekend(int $dayOfWeek): bool
    {
        return $dayOfWeek === Carbon::SATURDAY || $dayOfWeek === Carbon::SUNDAY;
    }

    /**
     * Distribución realista de estados para que el dashboard se vea jugoso:
     *  - 60% confirmed + paid (lo más común)
     *  - 15% completed + paid (partidos pasados)
     *  - 10% confirmed + unpaid (pago pendiente)
     *  - 8%  confirmed + payment_review (comprobante enviado)
     *  - 7%  cancelled + unpaid
     */
    private function pickStatusAndPayment(): array
    {
        $r = rand(1, 100);
        if ($r <= 60) {
            return ['confirmed', 'paid'];
        }
        if ($r <= 75) {
            return ['completed', 'paid'];
        }
        if ($r <= 85) {
            return ['confirmed', 'unpaid'];
        }
        if ($r <= 93) {
            return ['confirmed', 'payment_review'];
        }

        return ['cancelled', 'unpaid'];
    }

    private function randomNote(): ?string
    {
        $notes = [
            null, null, null, null, null, // 50% sin notas
            'Somos 10 personas, llevaremos petos',
            'Cumpleaños — necesitamos extra de bebidas',
            'Partido de despedida del equipo',
            'Primera vez en la cancha',
            'Trasnochamos, vamos relax',
            'Por favor tener listo el balón',
        ];

        return $notes[array_rand($notes)];
    }

    private function createDemoClients(): void
    {
        $demos = [
            ['Juan Restrepo',     'juan.restrepo@demo.com',   '3001112233'],
            ['Andrés Gómez',      'andres.gomez@demo.com',    '3002223344'],
            ['María Fernanda',    'maria.f@demo.com',         '3003334455'],
            ['Camilo Vargas',     'camilo.v@demo.com',        '3004445566'],
            ['Laura Castillo',    'laura.c@demo.com',         '3005556677'],
            ['Sebastián López',   'sebas.l@demo.com',         '3006667788'],
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
