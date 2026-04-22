<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 32); // platform | tenant
            $table->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();
            $table->string('snapshot_type', 64); // platform_health | governance_queue | tenant_operability
            $table->jsonb('aggregates');
            $table->jsonb('source_context')->nullable();
            $table->timestampTz('captured_at')->useCurrent();
            $table->timestampsTz();

            $table->index(['scope', 'snapshot_type', 'captured_at'], 'monitoring_snapshots_scope_type_captured_index');
            $table->index(['business_id', 'captured_at'], 'monitoring_snapshots_business_captured_index');
            $table->index('captured_at', 'monitoring_snapshots_captured_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_snapshots');
    }
};
