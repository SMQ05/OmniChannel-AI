<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNoDuplicateProviderRefs('billing_accounts', 'provider_driver', 'provider_account_ref');
        $this->assertNoDuplicateProviderRefs('billing_documents', 'provider_driver', 'provider_document_ref');
        $this->assertNoDuplicateProviderRefs('billing_transactions', 'provider_driver', 'provider_transaction_ref');
        $this->assertNoDuplicateProviderRefs('billing_transactions', 'provider_driver', 'provider_event_ref');

        Schema::table('billing_accounts', function (Blueprint $table): void {
            $table->dropIndex('billing_accounts_provider_ref_index');
            $table->unique(['provider_driver', 'provider_account_ref'], 'billing_accounts_provider_ref_unique');
        });

        Schema::table('billing_documents', function (Blueprint $table): void {
            $table->dropUnique('billing_documents_provider_ref_unique');
            $table->unique(['provider_driver', 'provider_document_ref'], 'billing_documents_provider_ref_unique');
        });

        Schema::table('billing_transactions', function (Blueprint $table): void {
            $table->dropUnique('billing_transactions_provider_tx_ref_unique');
            $table->dropUnique('billing_transactions_provider_event_ref_unique');
            $table->unique(['provider_driver', 'provider_transaction_ref'], 'billing_transactions_provider_tx_ref_unique');
            $table->unique(['provider_driver', 'provider_event_ref'], 'billing_transactions_provider_event_ref_unique');
        });

        $now = CarbonImmutable::now();
        $planCodes = DB::table('plans')->pluck('code', 'id');
        $businessPlans = DB::table('businesses')->pluck('plan', 'id');

        DB::table('business_subscriptions')
            ->select([
                'id',
                'business_id',
                'plan_id',
                'lifecycle_status',
                'current_period_end',
                'cancel_at_period_end',
                'canceled_at',
                'ended_at',
                'suspended_at',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($planCodes, $businessPlans, $now): void {
                foreach ($subscriptions as $subscription) {
                    $planCode = $subscription->plan_id !== null
                        ? $planCodes->get($subscription->plan_id)
                        : $businessPlans->get($subscription->business_id);

                    $currentPeriodEnd = $subscription->current_period_end !== null
                        ? CarbonImmutable::parse((string) $subscription->current_period_end)
                        : null;

                    $nextStatus = match (true) {
                        $subscription->lifecycle_status === 'past_due' => 'past_due',
                        $subscription->suspended_at !== null || $subscription->lifecycle_status === 'suspended' => 'suspended',
                        $subscription->canceled_at !== null || $subscription->lifecycle_status === 'canceled' => 'canceled',
                        $subscription->ended_at !== null || $subscription->lifecycle_status === 'expired' => 'expired',
                        (bool) $subscription->cancel_at_period_end
                            && $currentPeriodEnd !== null
                            && $currentPeriodEnd->lessThanOrEqualTo($now) => 'expired',
                        $planCode === 'trial' => 'trial',
                        default => 'active',
                    };

                    DB::table('business_subscriptions')
                        ->where('id', $subscription->id)
                        ->update([
                            'lifecycle_status' => $nextStatus,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('billing_accounts', function (Blueprint $table): void {
            $table->dropUnique('billing_accounts_provider_ref_unique');
            $table->index(['provider_driver', 'provider_account_ref'], 'billing_accounts_provider_ref_index');
        });

        Schema::table('billing_documents', function (Blueprint $table): void {
            $table->dropUnique('billing_documents_provider_ref_unique');
            $table->unique(['business_id', 'provider_driver', 'provider_document_ref'], 'billing_documents_provider_ref_unique');
        });

        Schema::table('billing_transactions', function (Blueprint $table): void {
            $table->dropUnique('billing_transactions_provider_tx_ref_unique');
            $table->dropUnique('billing_transactions_provider_event_ref_unique');
            $table->unique(['business_id', 'provider_driver', 'provider_transaction_ref'], 'billing_transactions_provider_tx_ref_unique');
            $table->unique(['business_id', 'provider_driver', 'provider_event_ref'], 'billing_transactions_provider_event_ref_unique');
        });
    }

    private function assertNoDuplicateProviderRefs(string $table, string $driverColumn, string $refColumn): void
    {
        $duplicate = DB::table($table)
            ->select([$driverColumn, $refColumn])
            ->whereNotNull($driverColumn)
            ->whereNotNull($refColumn)
            ->groupBy($driverColumn, $refColumn)
            ->havingRaw('COUNT(*) > 1')
            ->first();

        if ($duplicate === null) {
            return;
        }

        throw new \RuntimeException(sprintf(
            'Duplicate provider reference detected in %s for %s=%s and %s=%s. Resolve duplicates before applying this migration.',
            $table,
            $driverColumn,
            (string) $duplicate->{$driverColumn},
            $refColumn,
            (string) $duplicate->{$refColumn},
        ));
    }
};
