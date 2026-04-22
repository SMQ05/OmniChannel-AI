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
            // Add columns to track launch readiness data at subscription level
            // This is still derived from other sources - not a new truth source

            $table->boolean('has_onboarding_complete')->default(false)->after('lifecycle_status');
            $table->timestampTz('onboarding_completed_at')->nullable()->after('has_onboarding_complete');
            $table->boolean('has_all_channels_configured')->default(false)->after('has_onboarding_complete');
            $table->timestampTz('all_channels_configured_at')->nullable()->after('has_all_channels_configured');
            $table->boolean('has_billing_configured')->default(false)->after('has_all_channels_configured');
            $table->timestampTz('billing_configured_at')->nullable()->after('has_billing_configured');

            // Admin override flags
            $table->boolean('can_override_readiness_checks')->default(false)->after('has_billing_configured');
        });
    }

    public function down(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'has_onboarding_complete',
                'onboarding_completed_at',
                'has_all_channels_configured',
                'all_channels_configured_at',
                'has_billing_configured',
                'billing_configured_at',
                'can_override_readiness_checks',
            ]);
        });
    }
};
