<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 32);
            $table->string('role', 32);
            $table->string('permission_key', 120);
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['scope', 'role', 'permission_key'], 'role_permissions_scope_role_key_unique');
            $table->index(['scope', 'role'], 'role_permissions_scope_role_index');
            $table->index('permission_key', 'role_permissions_key_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permissions');
    }
};
