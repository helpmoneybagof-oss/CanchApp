<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ampliar el enum de status en time_slots para incluir pre_reserved.
        // SQLite no soporta ALTER COLUMN para enums — en tests/CI lo saltamos.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE time_slots MODIFY COLUMN status ENUM('available','pre_reserved','reserved','blocked') NOT NULL DEFAULT 'available'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE time_slots MODIFY COLUMN status ENUM('available','reserved','blocked') NOT NULL DEFAULT 'available'");
        }
    }
};
