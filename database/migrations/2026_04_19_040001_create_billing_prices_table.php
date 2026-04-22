<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('code', 64);
            $table->unsignedInteger('version')->default(1);
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('recurring_amount_minor')->nullable();
            $table->string('recurring_interval_unit', 16)->nullable();
            $table->unsignedInteger('recurring_interval_count')->nullable();
            $table->bigInteger('setup_fee_amount_minor')->nullable();
            $table->string('setup_fee_behavior', 32)->default('invoice_once');
            $table->unsignedInteger('trial_days')->nullable();
            $table->foreignId('supersedes_billing_price_id')->nullable()->constrained('billing_prices')->nullOnDelete();
            $table->string('provider_driver', 64)->nullable();
            $table->string('provider_sellable_ref', 191)->nullable();
            $table->string('provider_variant_ref', 191)->nullable();
            $table->json('provider_metadata')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['code', 'version'], 'billing_prices_code_version_unique');
            $table->index(['plan_id', 'status'], 'billing_prices_plan_status_index');
            $table->index(['provider_driver', 'provider_sellable_ref'], 'billing_prices_provider_sellable_index');
            $table->index(['provider_driver', 'provider_variant_ref'], 'billing_prices_provider_variant_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_prices');
    }
};
