<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_hours')->default(1); // cuántas horas reservó
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed'])->default('pending');
            $table->enum('payment_status', ['unpaid', 'paid'])->default('unpaid');
            $table->decimal('court_price', 8, 2)->default(0);   // precio de la cancha
            $table->decimal('consumables_price', 8, 2)->default(0); // precio de consumibles
            $table->decimal('total_price', 8, 2)->default(0);   // total general
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('confirmation_code')->unique()->nullable(); // código único de reserva
            $table->timestamps();

            $table->index(['date', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
