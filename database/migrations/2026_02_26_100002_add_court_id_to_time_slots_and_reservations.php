<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar court_id a time_slots
        Schema::table('time_slots', function (Blueprint $table) {
            $table->foreignId('court_id')->nullable()->after('id')->constrained()->nullOnDelete();

            // Eliminar el unique anterior (date, start_time) — ya no es único sin la cancha
            $table->dropUnique(['date', 'start_time']);

            // Nuevo unique: un slot por cancha + fecha + hora inicio
            $table->unique(['court_id', 'date', 'start_time']);
            $table->index(['court_id', 'date', 'status']);
        });

        // Agregar court_id a reservations
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('court_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index(['court_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('time_slots', function (Blueprint $table) {
            $table->dropForeign(['court_id']);
            $table->dropIndex(['court_id', 'date', 'start_time']);
            $table->dropIndex(['court_id', 'date', 'status']);
            $table->dropColumn('court_id');
            $table->unique(['date', 'start_time']);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropForeign(['court_id']);
            $table->dropIndex(['court_id', 'date']);
            $table->dropColumn('court_id');
        });
    }
};
