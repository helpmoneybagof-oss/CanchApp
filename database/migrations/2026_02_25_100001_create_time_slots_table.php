<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_slots', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->enum('status', ['available', 'reserved', 'blocked'])->default('available');
            $table->decimal('price', 8, 2)->default(0);
            $table->string('block_reason')->nullable(); // razón de bloqueo por admin
            $table->timestamps();

            // Un slot es único por fecha + hora inicio
            $table->unique(['date', 'start_time']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('time_slots');
    }
};
