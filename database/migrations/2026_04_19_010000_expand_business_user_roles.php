<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin', 'business_owner', 'manager', 'receptionist', 'staff', 'support_admin'))");
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','business_owner','manager','receptionist','staff','support_admin') NOT NULL DEFAULT 'staff'");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check");
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin', 'business_owner', 'staff'))");
            DB::statement("UPDATE users SET role = 'staff' WHERE role IN ('manager', 'receptionist', 'support_admin')");
            return;
        }

        if ($driver === 'mysql') {
            DB::statement("UPDATE users SET role = 'staff' WHERE role IN ('manager', 'receptionist', 'support_admin')");
            DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','business_owner','staff') NOT NULL DEFAULT 'staff'");
        }
    }
};
