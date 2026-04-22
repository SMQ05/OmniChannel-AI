<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_retention_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('run_key');
            $table->string('mode', 16); // dry_run | apply
            $table->string('scope', 16); // all | business
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('started'); // started | completed | failed | skipped
            $table->jsonb('summary')->nullable();
            $table->string('command_signature', 255);
            $table->timestampTz('started_at')->useCurrent();
            $table->timestampTz('finished_at')->nullable();
            $table->text('error')->nullable();
            $table->timestampsTz();

            $table->unique('run_key', 'data_retention_runs_run_key_unique');
            $table->index(['status', 'started_at'], 'data_retention_runs_status_started_index');
            $table->index(['business_id', 'started_at'], 'data_retention_runs_business_started_index');
            $table->index(['mode', 'started_at'], 'data_retention_runs_mode_started_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_runs');
    }
};
