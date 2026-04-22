<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')
                ->nullable()
                ->constrained('businesses')
                ->nullOnDelete();

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('actor_role', 32)->nullable();
            $table->string('action', 120);
            $table->string('subject_type', 160);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->uuid('request_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['business_id', 'created_at'], 'audit_logs_business_created_at_index');
            $table->index(['actor_user_id', 'created_at'], 'audit_logs_actor_created_at_index');
            $table->index(['subject_type', 'subject_id'], 'audit_logs_subject_index');
            $table->index(['action', 'created_at'], 'audit_logs_action_created_at_index');
            $table->index('request_id', 'audit_logs_request_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
