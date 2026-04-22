<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_service_provider', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_service_id')->constrained('business_services')->cascadeOnDelete();
            $table->foreignId('provider_id')->constrained('providers')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['business_service_id', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_service_provider');
    }
};
