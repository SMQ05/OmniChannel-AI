<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_worker_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->string('worker_name')->unique();
            $table->string('queue_connection');
            $table->string('queue_name');
            $table->string('host_name');
            $table->unsignedBigInteger('process_id')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('conversation_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_log_id')->constrained('conversation_logs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_notes');
        Schema::dropIfExists('queue_worker_heartbeats');
    }
};
