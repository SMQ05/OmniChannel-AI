<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('business_slug');
            $table->uuid('correlation_id');
            $table->string('idempotency_key', 64);
            $table->string('external_message_id')->nullable();
            $table->string('sender_platform_id')->nullable();
            $table->string('sender_name')->nullable();
            $table->text('message_text')->nullable();
            $table->string('message_type', 50)->nullable();
            $table->json('payload');
            $table->json('normalized_payload')->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('status', 32)->default('received');
            $table->string('queue_connection')->nullable();
            $table->string('queue_name')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('last_replayed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key']);
            $table->index(['business_id', 'channel', 'status']);
            $table->index(['external_message_id', 'channel']);
        });

        Schema::create('outbound_message_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('conversation_log_id')->nullable()->constrained('conversation_logs')->nullOnDelete();
            $table->foreignId('inbound_webhook_id')->nullable()->constrained('inbound_webhooks')->nullOnDelete();
            $table->string('channel', 32);
            $table->string('recipient_platform_id');
            $table->string('idempotency_key', 128);
            $table->uuid('correlation_id');
            $table->string('status', 32)->default('pending');
            $table->text('message_text');
            $table->string('provider_message_id')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('failure_class', 32)->nullable();
            $table->longText('response_body')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'channel', 'idempotency_key'], 'outbound_attempt_dedupe_unique');
            $table->index(['business_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_message_attempts');
        Schema::dropIfExists('inbound_webhooks');
    }
};
