<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('notifications')->truncate();
        DB::table('reservation_items')->truncate();
        DB::table('reservation_time_slots')->truncate();
        DB::table('reservations')->truncate();
        DB::table('time_slots')->truncate();
        DB::table('push_subscriptions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function down(): void
    {
        // No reversible
    }
};
