<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Ej: "Cancha Fútbol 5"
            $table->string('type')->nullable();              // Ej: "Fútbol 5", "Fútbol 9", "Fútbol 11"
            $table->decimal('price_per_hour', 8, 2)->default(0);
            $table->text('description')->nullable();
            $table->integer('capacity')->nullable();         // Nº de jugadores por equipo
            $table->string('surface')->nullable();           // "Sintética", "Natural", "Cemento"
            $table->integer('start_hour')->default(17);      // Hora inicio disponibilidad (5 PM)
            $table->integer('end_hour')->default(23);        // Hora fin (11 PM, último slot a las 10 PM)
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courts');
    }
};
