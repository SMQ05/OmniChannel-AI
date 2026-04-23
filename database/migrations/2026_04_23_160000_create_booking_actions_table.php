<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('action', 32);
            $table->string('source_channel', 32);
            $table->string('idempotency_key', 191);
            $table->string('status', 32)->default('processing');
            $table->json('request_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'idempotency_key'], 'booking_actions_business_idempotency_unique');
            $table->index(['business_id', 'action', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_actions');
    }
};
