<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the patients table.
 *
 * A patient is identified within a tenant by their messaging
 * platform identity (platform_user_id + platform + business_id).
 * The unique constraint prevents duplicate patient records for the
 * same person on the same channel within the same business.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();

            // WhatsApp user ID or Messenger PSID
            $table->string('platform_user_id');

            $table->enum('platform', ['whatsapp', 'messenger']);
            $table->text('notes')->nullable();
            $table->timestamps();

            // A patient is unique per channel identity within a tenant
            $table->unique(['business_id', 'platform_user_id', 'platform']);
            $table->index('business_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
