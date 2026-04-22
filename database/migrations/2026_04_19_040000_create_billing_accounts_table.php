<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->string('provider_driver', 64)->nullable();
            $table->string('provider_account_ref', 191)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('billing_email')->nullable();
            $table->string('invoice_email')->nullable();
            $table->string('collection_status', 32)->default('unconfigured');
            $table->string('default_payment_state', 32)->default('unknown');
            $table->boolean('portal_capable')->default(false);
            $table->json('provider_metadata')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();

            $table->index(['provider_driver', 'provider_account_ref'], 'billing_accounts_provider_ref_index');
            $table->index(['collection_status', 'portal_capable'], 'billing_accounts_collection_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_accounts');
    }
};
