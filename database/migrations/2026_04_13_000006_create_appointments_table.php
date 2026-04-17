<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the appointments table.
 *
 * The core booking record. reminder_sent_at tracks which reminder
 * offsets have already been dispatched (keyed by offset_hours) to
 * prevent duplicate reminder sends across scheduler runs.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('provider_id')
                ->constrained('providers')
                ->restrictOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            $table->string('service_type');
            $table->timestamp('start_time');
            $table->timestamp('end_time');

            $table->enum('status', [
                'pending',
                'confirmed',
                'cancelled',
                'completed',
                'no_show',
            ])->default('confirmed');

            $table->enum('booked_via', [
                'whatsapp',
                'messenger',
                'manual',
            ])->default('whatsapp');

            /**
             * Tracks which reminders have been sent.
             * Shape: { "24": "2026-04-19T14:00:00Z", "2": null }
             * Keyed by offset_hours; value is ISO timestamp or null.
             */
            $table->jsonb('reminder_sent_at')->nullable();

            $table->boolean('synced_to_calendar')->default(false);
            $table->boolean('synced_to_sheets')->default(false);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status', 'start_time']);
            $table->index(['provider_id', 'start_time']);
            $table->index(['patient_id', 'start_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
