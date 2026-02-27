<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Método de pago elegido por el cliente
            $table->string('payment_method')->nullable()->after('payment_status');
            // Referencia o número de comprobante ingresado por el cliente
            $table->string('payment_reference')->nullable()->after('payment_method');
            // Ruta del archivo de comprobante subido
            $table->string('payment_proof')->nullable()->after('payment_reference');
            // Fecha/hora en que expira la reserva si no se paga
            $table->timestamp('payment_expires_at')->nullable()->after('payment_proof');
        });

        // Ampliar el enum payment_status para estados intermedios de pago
        // SQLite no soporta ALTER COLUMN para enums, usamos string
        // En MySQL: modificar la columna enum directamente
        DB::statement("ALTER TABLE reservations MODIFY COLUMN payment_status ENUM('unpaid','pending_payment','payment_review','paid','rejected') NOT NULL DEFAULT 'unpaid'");
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_reference', 'payment_proof', 'payment_expires_at']);
        });

        DB::statement("ALTER TABLE reservations MODIFY COLUMN payment_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid'");
    }
};
