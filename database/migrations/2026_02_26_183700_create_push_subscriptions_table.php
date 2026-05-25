<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('push_subscriptions')) {
            return;
        }

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500);
            $table->text('p256dh');    // clave pública del cliente
            $table->text('auth');      // clave de autenticación
            $table->timestamps();

            // Evitar suscripciones duplicadas por endpoint (primeros 191 chars)
            $table->unique(\Illuminate\Support\Facades\DB::raw('endpoint(191)'), 'push_subscriptions_endpoint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
