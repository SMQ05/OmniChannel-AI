<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the provider_blocked_dates table.
 *
 * Records individual dates on which a provider is unavailable
 * (holidays, leave, etc.). The slot-availability calculator
 * excludes these dates before offering slots to the AI agent.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provider_blocked_dates', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('provider_id')
                ->constrained('providers')
                ->cascadeOnDelete();

            $table->date('blocked_date');
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['provider_id', 'blocked_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_blocked_dates');
    }
};
