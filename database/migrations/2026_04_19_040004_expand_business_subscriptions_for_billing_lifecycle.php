<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->string('lifecycle_status', 32)->nullable()->after('status');
            $table->foreignId('billing_price_id')->nullable()->after('plan_id')->constrained('billing_prices')->nullOnDelete();
            $table->json('price_snapshot')->nullable()->after('feature_flags');
            $table->timestampTz('billing_cycle_anchor_at')->nullable()->after('current_period_end');
            $table->timestampTz('next_invoice_at')->nullable()->after('billing_cycle_anchor_at');
            $table->timestampTz('trial_ends_at')->nullable()->after('next_invoice_at');
            $table->timestampTz('grace_ends_at')->nullable()->after('trial_ends_at');
            $table->boolean('cancel_at_period_end')->default(false)->after('grace_ends_at');
            $table->timestampTz('cancel_requested_at')->nullable()->after('cancel_at_period_end');
            $table->timestampTz('canceled_at')->nullable()->after('cancel_requested_at');
            $table->timestampTz('ended_at')->nullable()->after('canceled_at');
            $table->timestampTz('past_due_at')->nullable()->after('ended_at');
            $table->timestampTz('suspended_at')->nullable()->after('past_due_at');
            $table->timestampTz('reactivated_at')->nullable()->after('suspended_at');
            $table->timestampTz('setup_fee_invoiced_at')->nullable()->after('reactivated_at');
            $table->string('suspension_reason', 255)->nullable()->after('setup_fee_invoiced_at');
            $table->string('provider_contract_ref', 191)->nullable()->after('suspension_reason');
            $table->json('provider_metadata')->nullable()->after('provider_contract_ref');

            $table->index(['lifecycle_status', 'next_invoice_at'], 'business_subscriptions_lifecycle_invoice_index');
            $table->index(['billing_price_id', 'current_period_end'], 'business_subscriptions_price_period_index');
        });
    }

    public function down(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('business_subscriptions_lifecycle_invoice_index');
            $table->dropIndex('business_subscriptions_price_period_index');
            $table->dropConstrainedForeignId('billing_price_id');
            $table->dropColumn([
                'lifecycle_status',
                'price_snapshot',
                'billing_cycle_anchor_at',
                'next_invoice_at',
                'trial_ends_at',
                'grace_ends_at',
                'cancel_at_period_end',
                'cancel_requested_at',
                'canceled_at',
                'ended_at',
                'past_due_at',
                'suspended_at',
                'reactivated_at',
                'setup_fee_invoiced_at',
                'suspension_reason',
                'provider_contract_ref',
                'provider_metadata',
            ]);
        });
    }
};
