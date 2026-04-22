<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('billing_account_id')->nullable()->constrained('billing_accounts')->nullOnDelete();
            $table->foreignId('business_subscription_id')->nullable()->constrained('business_subscriptions')->nullOnDelete();
            $table->string('type', 32)->default('invoice');
            $table->string('status', 32)->default('draft');
            $table->string('document_key', 191);
            $table->string('number', 64)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('subtotal_minor')->default(0);
            $table->bigInteger('credit_total_minor')->default(0);
            $table->bigInteger('tax_total_minor')->default(0);
            $table->bigInteger('total_minor')->default(0);
            $table->bigInteger('amount_due_minor')->default(0);
            $table->bigInteger('amount_paid_minor')->default(0);
            $table->timestampTz('period_start')->nullable();
            $table->timestampTz('period_end')->nullable();
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('voided_at')->nullable();
            $table->string('provider_driver', 64)->nullable();
            $table->string('provider_document_ref', 191)->nullable();
            $table->string('source', 32)->default('internal');
            $table->json('context_snapshot')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestampsTz();

            $table->unique('document_key', 'billing_documents_document_key_unique');
            $table->unique(['business_id', 'provider_driver', 'provider_document_ref'], 'billing_documents_provider_ref_unique');
            $table->index(['business_id', 'type', 'status'], 'billing_documents_business_type_status_index');
            $table->index(['business_subscription_id', 'period_start', 'period_end'], 'billing_documents_subscription_period_index');
            $table->index(['business_id', 'number'], 'billing_documents_business_number_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_documents');
    }
};
