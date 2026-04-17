<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the providers table.
 *
 * A provider is the bookable resource within a business
 * (doctor, stylist, lawyer, therapist, etc.).
 * Working hours are stored as a JSON map keyed by lowercase
 * day name, with optional start/end times and an active flag.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('title')->nullable();
            $table->string('specialization')->nullable();

            /**
             * Weekly schedule.
             * Shape: { monday: { start, end, active }, tuesday: { … }, … }
             */
            $table->jsonb('working_hours')->nullable();

            $table->unsignedSmallInteger('slot_duration_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('business_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};
