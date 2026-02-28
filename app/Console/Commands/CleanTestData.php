<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanTestData extends Command
{
    protected $signature = 'app:clean-test-data';
    protected $description = 'Limpia todas las reservas, slots y notificaciones de prueba';

    public function handle(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('notifications')->truncate();
        DB::table('reservation_items')->truncate();
        DB::table('reservation_time_slots')->truncate();
        DB::table('reservations')->truncate();
        DB::table('time_slots')->truncate();
        DB::table('push_subscriptions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->info('✅ Limpieza completa.');
    }
}
