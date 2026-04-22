<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $timestamp = now();
        $backfillMarker = '2026_04_19_040009';

        DB::table('plans')
            ->select(['id', 'code', 'name', 'description', 'included_quotas'])
            ->orderBy('id')
            ->chunkById(100, function ($plans) use ($timestamp, $backfillMarker): void {
                foreach ($plans as $plan) {
                    $includedQuotas = $this->decodeJson($plan->included_quotas);
                    $existingBillingPrice = DB::table('billing_prices')
                        ->select('id')
                        ->where('code', (string) $plan->code)
                        ->where('version', 1)
                        ->first();

                    if ($existingBillingPrice === null) {
                        DB::table('billing_prices')->insert([
                            'plan_id' => $plan->id,
                            'code' => (string) $plan->code,
                            'version' => 1,
                            'name' => $plan->name,
                            'description' => $plan->description,
                            'status' => 'draft',
                            'currency' => 'USD',
                            'recurring_amount_minor' => null,
                            'recurring_interval_unit' => null,
                            'recurring_interval_count' => null,
                            'setup_fee_amount_minor' => null,
                            'setup_fee_behavior' => 'invoice_once',
                            'trial_days' => null,
                            'supersedes_billing_price_id' => null,
                            'provider_driver' => null,
                            'provider_sellable_ref' => null,
                            'provider_variant_ref' => null,
                            'provider_metadata' => null,
                            'metadata' => json_encode([
                                'backfilled_from_plan_catalog' => true,
                                'backfill_marker' => $backfillMarker,
                                'source_plan_id' => $plan->id,
                            ], JSON_THROW_ON_ERROR),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);
                    }

                    if (!array_key_exists('messaging_credit_usd', $includedQuotas)) {
                        continue;
                    }

                    $billingPriceId = DB::table('billing_prices')
                        ->where('code', (string) $plan->code)
                        ->where('version', 1)
                        ->value('id');

                    if ($billingPriceId === null) {
                        continue;
                    }

                    $existingCreditPolicy = DB::table('billing_price_credit_policies')
                        ->select('id')
                        ->where('billing_price_id', $billingPriceId)
                        ->where('code', 'messaging-credit')
                        ->first();

                    if ($existingCreditPolicy !== null) {
                        continue;
                    }

                    $creditAmount = $this->parseMinorAmount($includedQuotas['messaging_credit_usd']);

                    DB::table('billing_price_credit_policies')->insert([
                        'billing_price_id' => $billingPriceId,
                        'code' => 'messaging-credit',
                        'balance_bucket' => 'messaging_credit',
                        'currency' => 'USD',
                        'amount_minor' => $creditAmount,
                        'grant_cadence' => 'per_billing_cycle',
                        'expires_with_period' => true,
                        'carry_forward' => false,
                        'metadata' => json_encode([
                            'backfilled_from_plan_quota_key' => 'messaging_credit_usd',
                            'backfill_marker' => $backfillMarker,
                            'source_plan_id' => $plan->id,
                        ], JSON_THROW_ON_ERROR),
                        'is_active' => true,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
                }
            });

        $planCodes = DB::table('plans')->pluck('code', 'id');
        $businessPlans = DB::table('businesses')->pluck('plan', 'id');

        DB::table('business_subscriptions')
            ->select(['id', 'business_id', 'plan_id', 'current_period_end'])
            ->orderBy('id')
            ->chunkById(100, function ($subscriptions) use ($planCodes, $businessPlans, $timestamp): void {
                foreach ($subscriptions as $subscription) {
                    $planCode = $subscription->plan_id !== null
                        ? $planCodes->get($subscription->plan_id)
                        : $businessPlans->get($subscription->business_id);

                    $lifecycleStatus = $planCode === 'trial' ? 'trial' : 'active';

                    DB::table('business_subscriptions')
                        ->where('id', $subscription->id)
                        ->update([
                            'lifecycle_status' => $lifecycleStatus,
                            'updated_at' => $timestamp,
                        ]);
                }
            });
    }

    public function down(): void
    {
        $backfillMarker = '2026_04_19_040009';

        DB::table('business_subscriptions')->update([
            'lifecycle_status' => null,
        ]);

        $creditPolicyIds = DB::table('billing_price_credit_policies')
            ->select(['id', 'metadata'])
            ->get()
            ->filter(fn (object $policy): bool => $this->hasBackfillMarker($policy->metadata, $backfillMarker))
            ->pluck('id')
            ->all();

        if ($creditPolicyIds !== []) {
            DB::table('billing_price_credit_policies')
                ->whereIn('id', $creditPolicyIds)
                ->delete();
        }

        $billingPriceIds = DB::table('billing_prices')
            ->select(['id', 'metadata'])
            ->get()
            ->filter(fn (object $price): bool => $this->hasBackfillMarker($price->metadata, $backfillMarker))
            ->pluck('id')
            ->all();

        if ($billingPriceIds !== []) {
            DB::table('billing_prices')
                ->whereIn('id', $billingPriceIds)
                ->delete();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        /** @var mixed $decoded */
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function hasBackfillMarker(mixed $value, string $marker): bool
    {
        $decoded = $this->decodeJson($value);

        return ($decoded['backfill_marker'] ?? null) === $marker;
    }

    private function parseMinorAmount(mixed $value): int
    {
        if (is_int($value)) {
            return max($value, 0) * 100;
        }

        if (is_float($value)) {
            return max((int) round($value * 100), 0);
        }

        if (is_string($value)) {
            $trimmed = trim($value);

            if ($trimmed !== '' && is_numeric($trimmed)) {
                return max((int) round(((float) $trimmed) * 100), 0);
            }
        }

        return 0;
    }
};
