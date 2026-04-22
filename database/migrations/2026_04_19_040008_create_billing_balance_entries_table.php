<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_balance_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('billing_account_id')->nullable()->constrained('billing_accounts')->nullOnDelete();
            $table->foreignId('business_subscription_id')->nullable()->constrained('business_subscriptions')->nullOnDelete();
            $table->foreignId('billing_document_id')->nullable()->constrained('billing_documents')->nullOnDelete();
            $table->foreignId('billing_transaction_id')->nullable()->constrained('billing_transactions')->nullOnDelete();
            $table->string('entry_key', 191);
            $table->string('entry_type', 32);
            $table->string('balance_bucket', 64);
            $table->string('direction', 16);
            $table->string('currency', 3)->default('USD');
            $table->bigInteger('amount_minor')->default(0);
            $table->timestampTz('effective_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->foreignId('applies_to_entry_id')->nullable()->constrained('billing_balance_entries')->nullOnDelete();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();

            $table->unique('entry_key', 'billing_balance_entries_entry_key_unique');
            $table->index(['business_id', 'balance_bucket', 'effective_at'], 'billing_balance_entries_bucket_effective_index');
            $table->index(['billing_account_id', 'direction'], 'billing_balance_entries_account_direction_index');
            $table->index(['business_subscription_id', 'entry_type'], 'billing_balance_entries_subscription_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_balance_entries');
    }
};
