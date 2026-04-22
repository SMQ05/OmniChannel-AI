<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_document_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_document_id')->constrained('billing_documents')->cascadeOnDelete();
            $table->foreignId('billing_price_id')->nullable()->constrained('billing_prices')->nullOnDelete();
            $table->string('line_type', 32);
            $table->string('metric', 80)->nullable();
            $table->text('description');
            $table->decimal('quantity', 14, 4)->default(0);
            $table->bigInteger('unit_amount_minor')->nullable();
            $table->bigInteger('subtotal_minor')->default(0);
            $table->timestampTz('period_start')->nullable();
            $table->timestampTz('period_end')->nullable();
            $table->string('source_key', 191)->nullable();
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('snapshot')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestampsTz();

            $table->unique('source_key', 'billing_document_lines_source_key_unique');
            $table->index(['billing_document_id', 'line_type'], 'billing_document_lines_document_type_index');
            $table->index(['metric', 'line_type'], 'billing_document_lines_metric_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_document_lines');
    }
};
