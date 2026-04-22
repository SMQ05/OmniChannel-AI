<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the users table.
 *
 * Users belong to a business (tenant) and carry either business_owner,
 * manager, receptionist, or staff. Platform users can also be
 * super_admin or support_admin and have a NULL business_id.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')
                ->nullable()
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            $table->enum('role', [
                'super_admin',
                'support_admin',
                'business_owner',
                'manager',
                'receptionist',
                'staff',
            ])->default('staff');

            $table->rememberToken();
            $table->timestamps();

            $table->index('business_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
