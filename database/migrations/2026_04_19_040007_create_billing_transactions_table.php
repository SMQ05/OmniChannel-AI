<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('billing_account_id')->nullable()->constrained('billing_accounts')->nullOnDelete();
            $table->foreignId('billing_document_id')->nullable()->constrained('billing_documents')->nullOnDelete();
            $table->foreignId('business_subscription_id')->nullable()->constrained('business_subscriptions')->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 32)->default('pending');
            $table->string('direction', 16)->default('inbound');
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('amount_minor')->default(0);
            $table->string('provider_driver', 64)->nullable();
            $table->string('provider_transaction_ref', 191)->nullable();
            $table->string('provider_event_ref', 191)->nullable();
            $table->string('idempotency_key', 191)->nullable();
            $table->timestampTz('effective_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['business_id', 'provider_driver', 'provider_transaction_ref'], 'billing_transactions_provider_tx_ref_unique');
            $table->unique(['business_id', 'provider_driver', 'provider_event_ref'], 'billing_transactions_provider_event_ref_unique');
            $table->unique('idempotency_key', 'billing_transactions_idempotency_key_unique');
            $table->index(['business_id', 'status'], 'billing_transactions_business_status_index');
            $table->index(['business_subscription_id', 'effective_at'], 'billing_transactions_subscription_effective_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_transactions');
    }
};
