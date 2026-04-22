<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credential_metadata', function (Blueprint $table): void {
            $table->id();

            // Reference to the credential source
            $table->string('source_type', 64); // 'messaging_connection' | 'voice_channel' | 'billing_account'
            $table->unsignedBigInteger('source_id');
            $table->string('provider', 64); // 'meta_cloud' | 'twilio' | 'deepgram' | 'openrouter' | 'elevenlabs' | etc.

            // Metadata only - NEVER store actual secrets here
            $table->string('key_name', 128); // descriptive name for the credential
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            // Rotation tracking
            $table->timestampTz('last_rotated_at')->nullable();
            $table->timestampTz('next_rotation_due_at')->nullable();
            $table->integer('rotation_interval_days')->default(90);

            // Last verification
            $table->timestampTz('last_verified_at')->nullable();
            $table->string('last_verification_status', 16)->nullable(); // 'pass' | 'fail' | 'unknown'
            $table->text('last_verification_message')->nullable();

            // Admin notes
            $table->foreignId('last_verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Provider-specific metadata (non-sensitive)
            $table->jsonb('metadata')->nullable();

            $table->timestampsTz();

            // Composite index for quick lookups
            $table->index(['source_type', 'source_id'], 'credential_metadata_source_index');
            $table->index('provider', 'credential_metadata_provider_index');
            $table->index('last_rotated_at', 'credential_metadata_rotation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credential_metadata');
    }
};
