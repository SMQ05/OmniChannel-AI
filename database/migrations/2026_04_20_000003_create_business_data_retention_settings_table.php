<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_data_retention_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->integer('conversation_logs_days')->nullable();
            $table->integer('inbound_webhooks_days')->nullable();
            $table->integer('outbound_attempts_days')->nullable();
            $table->integer('voice_events_days')->nullable();
            $table->string('deletion_strategy', 32)->default('anonymize'); // anonymize | hard_delete
            $table->timestampTz('legal_hold_until')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique('business_id', 'business_retention_settings_business_unique');
            $table->index('legal_hold_until', 'business_retention_settings_legal_hold_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_data_retention_settings');
    }
};
