<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->foreignId('service_id')
                ->nullable()
                ->after('patient_id')
                ->constrained('business_services')
                ->nullOnDelete();

            $table->index(['business_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'service_id']);
            $table->dropConstrainedForeignId('service_id');
        });
    }
};
