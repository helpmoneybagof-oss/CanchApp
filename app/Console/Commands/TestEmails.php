<?php

namespace App\Console\Commands;

use App\Jobs\SendDailySummary;
use App\Jobs\SendLowStockAlert;
use App\Jobs\SendReservationCancelled;
use App\Jobs\SendReservationConfirmed;
use App\Jobs\SendReservationReminder;
use App\Jobs\SendWelcomeEmail;
use App\Models\Court;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class TestEmails extends Command
{
    protected $signature = 'mail:test {--type=all : Tipo de correo (all, confirmed, cancelled, reminder, welcome, summary, stock)}
                                      {--to= : Email destino (usa el del primer usuario si no se especifica)}';

    protected $description = 'Envía correos de prueba para verificar las plantillas y la configuración SMTP';

    public function handle(): int
    {
        $type = $this->option('type');
        $toEmail = $this->option('to');

        // Obtener o crear usuario de prueba
        $user = User::where('role', 'client')->first();
        if (! $user) {
            $this->error('No hay usuarios cliente en la BD. Crea uno primero.');
            return self::FAILURE;
        }

        if ($toEmail) {
            $user->email = $toEmail;
        }

        $this->info("📧 Enviando correos de prueba a: {$user->email}");
        $this->info("🔌 Mailer: " . config('mail.default') . " → " . config('mail.mailers.smtp.host', 'N/A'));
        $this->newLine();

        // Obtener o simular reserva
        $reservation = $this->getOrFakeReservation($user);

        $methods = match ($type) {
            'confirmed' => ['sendConfirmed'],
            'cancelled' => ['sendCancelled'],
            'reminder'  => ['sendReminder'],
            'welcome'   => ['sendWelcome'],
            'summary'   => ['sendSummary'],
            'stock'     => ['sendStock'],
            default     => ['sendConfirmed', 'sendCancelled', 'sendReminder', 'sendWelcome', 'sendSummary', 'sendStock'],
        };

        foreach ($methods as $method) {
            $this->$method($user, $reservation);
        }

        $this->newLine();
        $this->info('✅ ¡Listo! Revisa tu bandeja en Mailtrap.');

        return self::SUCCESS;
    }

    private function send(string $label, callable $fn): void
    {
        $this->line("📨 Enviando: {$label}...");
        try {
            $fn();
            $this->info('   ✓ Enviado correctamente.');
        } catch (\Throwable $e) {
            $this->error('   ✗ Error: ' . $e->getMessage());
        }
        // Pausa de 2 segundos entre correos para respetar el rate limit del plan free de Mailtrap
        sleep(2);
    }

    private function sendConfirmed(User $user, Reservation $reservation): void
    {
        // Solo el correo al cliente (sin notificar al admin) para evitar rate limit en pruebas
        $this->line('📨 Enviando: Reserva Confirmada al cliente (con PDF adjunto)...');
        try {
            $reservation->load(['court', 'items.product', 'user']);
            \Illuminate\Support\Facades\Mail::to($user->email)
                ->send(new \App\Mail\ReservationConfirmed($reservation));
            $this->info('   ✓ Enviado correctamente.');
        } catch (\Throwable $e) {
            $this->error('   ✗ Error: ' . $e->getMessage());
        }
        sleep(3);
    }

    private function sendCancelled(User $user, Reservation $reservation): void
    {
        $this->send('Reserva Cancelada', fn () =>
            (new SendReservationCancelled($reservation))->handle()
        );
    }

    private function sendReminder(User $user, Reservation $reservation): void
    {
        $this->send('Recordatorio 24h', fn () =>
            (new SendReservationReminder($reservation, '24 horas'))->handle()
        );
        $this->send('Recordatorio 2h', fn () =>
            (new SendReservationReminder($reservation, '2 horas'))->handle()
        );
    }

    private function sendWelcome(User $user, Reservation $reservation): void
    {
        $this->send('Bienvenida al usuario', fn () =>
            (new SendWelcomeEmail($user))->handle()
        );
    }

    private function sendSummary(User $user, Reservation $reservation): void
    {
        $this->send('Resumen diario admin', fn () =>
            (new SendDailySummary())->handle()
        );
    }

    private function sendStock(User $user, Reservation $reservation): void
    {
        $this->line('📨 Enviando: Alerta de stock bajo...');
        try {
            (new SendLowStockAlert())->handle();
            $this->info('   ✓ Enviado. (Solo envía si hay productos con stock bajo)');
        } catch (\Throwable $e) {
            $this->error('   ✗ Error: ' . $e->getMessage());
        }
        sleep(2);
    }

    private function getOrFakeReservation(User $user): Reservation
    {
        // Intentar obtener una reserva real
        $real = Reservation::with(['court', 'items.product', 'user'])
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        if ($real) {
            $real->setRelation('user', $user);
            return $real;
        }

        // Crear una reserva fake (sin guardar en BD) para la prueba
        $court = Court::first();

        $reservation = new Reservation([
            'id'                => 999,
            'user_id'           => $user->id,
            'court_id'          => $court?->id,
            'date'              => Carbon::tomorrow()->toDateString(),
            'start_time'        => '10:00:00',
            'end_time'          => '11:00:00',
            'duration_hours'    => 1,
            'status'            => 'confirmed',
            'payment_status'    => 'unpaid',
            'court_price'       => $court?->price_per_hour ?? 50000,
            'consumables_price' => 0,
            'total_price'       => $court?->price_per_hour ?? 50000,
            'confirmation_code' => 'TEST' . strtoupper(Str::random(4)),
            'notes'             => null,
            'cancellation_reason' => null,
        ]);

        $reservation->setRelation('user', $user);
        $reservation->setRelation('court', $court ?? new Court(['id' => 1, 'name' => 'Cancha Principal']));
        $reservation->setRelation('items', collect());

        // Setear timestamps manualmente para evitar errores
        $reservation->created_at = now();
        $reservation->updated_at = now();

        return $reservation;
    }
}
