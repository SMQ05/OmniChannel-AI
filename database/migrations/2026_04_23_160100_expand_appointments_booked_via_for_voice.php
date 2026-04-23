<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY booked_via ENUM('whatsapp','messenger','manual','voice') NOT NULL DEFAULT 'whatsapp'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_booked_via_check");
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_booked_via_check CHECK (booked_via IN ('whatsapp', 'messenger', 'manual', 'voice'))");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE appointments MODIFY booked_via ENUM('whatsapp','messenger','manual') NOT NULL DEFAULT 'whatsapp'");

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE appointments DROP CONSTRAINT IF EXISTS appointments_booked_via_check");
            DB::statement("ALTER TABLE appointments ADD CONSTRAINT appointments_booked_via_check CHECK (booked_via IN ('whatsapp', 'messenger', 'manual'))");
        }
    }
};
