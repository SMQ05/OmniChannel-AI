<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('voice_channel_id')->nullable()->constrained('voice_channels')->nullOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('legacy_call_log_id')->nullable()->constrained('call_logs')->nullOnDelete();
            $table->string('provider', 32)->default('telnyx');
            $table->string('provider_call_id')->nullable()->index();
            $table->string('transport_stream_id')->nullable();
            $table->string('openai_session_id')->nullable();
            $table->string('deepgram_session_id')->nullable();
            $table->string('direction', 16)->default('inbound');
            $table->string('status', 32)->default('initializing');
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('transfer_requested_at')->nullable();
            $table->timestamp('callback_requested_at')->nullable();
            $table->string('fallback_mode', 32)->nullable();
            $table->string('handoff_reason')->nullable();
            $table->json('metrics')->nullable();
            $table->json('context')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'provider_call_id']);
        });

        Schema::create('voice_turns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voice_session_id')->constrained('voice_sessions')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('role', 24);
            $table->string('source', 32)->nullable();
            $table->longText('text')->nullable();
            $table->longText('transcript')->nullable();
            $table->string('tool_name', 64)->nullable();
            $table->string('tool_status', 32)->nullable();
            $table->boolean('interrupted')->default(false);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['voice_session_id', 'sequence']);
        });

        Schema::create('voice_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('voice_session_id')->constrained('voice_sessions')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('source', 32);
            $table->string('idempotency_key')->nullable();
            $table->string('correlation_id')->nullable();
            $table->string('severity', 16)->default('info');
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['voice_session_id', 'event_type', 'occurred_at']);
            $table->index(['business_id', 'occurred_at']);
            $table->index(['voice_session_id', 'idempotency_key']);
        });

        Schema::create('voice_summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voice_session_id')->unique()->constrained('voice_sessions')->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->string('disposition', 32)->nullable();
            $table->json('action_items')->nullable();
            $table->string('booking_outcome', 32)->nullable();
            $table->boolean('followup_required')->default(false);
            $table->json('structured_data')->nullable();
            $table->string('generated_by', 64)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('voice_usage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('voice_session_id')->constrained('voice_sessions')->cascadeOnDelete();
            $table->string('provider', 32)->nullable();
            $table->string('metric', 64);
            $table->decimal('quantity', 12, 3);
            $table->string('unit', 24);
            $table->decimal('cost_estimate', 12, 4)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['business_id', 'metric', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_usage_events');
        Schema::dropIfExists('voice_summaries');
        Schema::dropIfExists('voice_events');
        Schema::dropIfExists('voice_turns');
        Schema::dropIfExists('voice_sessions');
    }
};
