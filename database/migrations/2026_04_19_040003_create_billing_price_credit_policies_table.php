<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_price_credit_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_price_id')->constrained('billing_prices')->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('balance_bucket', 64);
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('amount_minor');
            $table->string('grant_cadence', 32)->default('per_billing_cycle');
            $table->boolean('expires_with_period')->default(true);
            $table->boolean('carry_forward')->default(false);
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['billing_price_id', 'code'], 'billing_price_credit_policies_price_code_unique');
            $table->index(['balance_bucket', 'is_active'], 'billing_price_credit_policies_bucket_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_price_credit_policies');
    }
};
