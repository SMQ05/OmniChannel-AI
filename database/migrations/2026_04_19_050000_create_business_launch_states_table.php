<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_launch_states', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();

            // Admin workflow state only - not runtime truth
            $table->string('launch_stage', 32)->default('onboarding');

            // Onboarding progress tracking
            $table->timestampTz('onboarding_started_at')->nullable();
            $table->timestampTz('onboarding_completed_at')->nullable();
            $table->timestampTz('launch_approved_at')->nullable();
            $table->timestampTz('live_at')->nullable();

            // Readiness flags (aggregate from other services, not source of truth)
            $table->boolean('is_messaging_ready')->default(false);
            $table->boolean('is_billing_ready')->default(false);
            $table->boolean('is_voice_ready')->default(false);
            $table->boolean('is_diagnostics_ready')->default(false);
            $table->boolean('is_operations_ready')->default(false);

            // Admin override
            $table->boolean('can_skip_readiness')->default(false);

            // Last snapshot timestamp
            $table->timestampTz('readiness_snapshot_at')->nullable();

            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_launch_states');
    }
};
