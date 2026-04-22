<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_governance_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->cascadeOnDelete();

            $table->string('request_scope', 32)->default('tenant'); // tenant | platform
            $table->string('request_type', 32); // export | delete
            $table->string('status', 32)->default('pending_approval');

            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executed_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->text('request_reason')->nullable();
            $table->text('approval_reason')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->jsonb('requested_filters')->nullable();
            $table->jsonb('execution_policy')->nullable();
            $table->jsonb('result_summary')->nullable();

            $table->string('artifact_disk', 64)->nullable();
            $table->string('artifact_path')->nullable();
            $table->timestampTz('artifact_expires_at')->nullable();

            $table->uuid('run_token')->nullable();
            $table->boolean('legal_hold_applied')->default(false);

            $table->timestampTz('requested_at')->useCurrent();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique('run_token', 'data_governance_requests_run_token_unique');
            $table->index(['status', 'request_type', 'created_at'], 'dgr_status_type_created_index');
            $table->index(['business_id', 'request_type', 'status'], 'dgr_business_type_status_index');
            $table->index(['requested_by_user_id', 'created_at'], 'dgr_requested_by_created_index');
            $table->index('artifact_expires_at', 'dgr_artifact_expires_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_governance_requests');
    }
};
