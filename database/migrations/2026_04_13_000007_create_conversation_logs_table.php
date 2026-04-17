<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the conversation_logs table.
 *
 * Each row represents a single patient conversation session.
 * The messages JSON array is the full chat history passed to the
 * AI agent on every turn. ai_model_used records the LLM that
 * served the session for audit and billing purposes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversation_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();

            $table->enum('channel', ['whatsapp', 'messenger']);

            /**
             * Full message history for this session.
             * Shape: [{ role, content, timestamp }]
             */
            $table->jsonb('messages')->nullable();

            // Which LLM served this session (e.g. "claude-sonnet-4-6")
            $table->string('ai_model_used')->nullable();

            $table->boolean('human_mode')->default(false);

            $table->timestamp('session_started_at');
            $table->timestamp('session_ended_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'patient_id']);
            $table->index(['business_id', 'channel']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_logs');
    }
};
