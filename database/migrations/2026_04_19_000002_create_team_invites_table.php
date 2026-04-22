<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_invites', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            $table->foreignId('invited_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('email');
            $table->string('role', 32);
            $table->string('token_hash', 128)->unique();
            $table->string('status', 32)->default('pending');
            $table->timestampTz('expires_at');

            $table->foreignId('accepted_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestampTz('accepted_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'email'], 'team_invites_business_email_index');
            $table->index(['business_id', 'status'], 'team_invites_business_status_index');
            $table->index('expires_at', 'team_invites_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invites');
    }
};
