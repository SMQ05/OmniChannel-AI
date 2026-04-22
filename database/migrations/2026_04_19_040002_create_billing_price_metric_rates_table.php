<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_price_metric_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_price_id')->constrained('billing_prices')->cascadeOnDelete();
            $table->string('metric', 80);
            $table->string('currency', 3)->default('USD');
            $table->string('billable_unit', 32);
            $table->decimal('unit_size', 14, 4)->default(1);
            $table->string('aggregation_strategy', 32)->default('sum_quantity');
            $table->string('rounding_mode', 16)->default('none');
            $table->string('pricing_model', 32)->default('per_unit');
            $table->bigInteger('unit_amount_minor')->nullable();
            $table->decimal('free_units', 14, 4)->nullable();
            $table->decimal('cap_units', 14, 4)->nullable();
            $table->string('balance_bucket', 64)->nullable();
            $table->json('event_filters')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->index(['billing_price_id', 'metric'], 'billing_price_metric_rates_price_metric_index');
            $table->index(['billing_price_id', 'is_active'], 'billing_price_metric_rates_price_active_index');
            $table->index(['metric', 'pricing_model'], 'billing_price_metric_rates_metric_pricing_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_price_metric_rates');
    }
};
