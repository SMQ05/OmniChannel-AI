<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingDocumentLine;
use App\Models\BillingPrice;
use App\Models\BillingPriceCreditPolicy;
use App\Models\BillingPriceMetricRate;
use App\Models\Business;
use App\Models\BusinessSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class BillingPriceCatalogService
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $metricRates
     * @param  array<int, array<string, mixed>>  $creditPolicies
     */
    public function store(
        array $attributes,
        array $metricRates = [],
        array $creditPolicies = [],
        ?BillingPrice $existing = null,
    ): BillingPrice {
        return DB::transaction(function () use ($attributes, $metricRates, $creditPolicies, $existing): BillingPrice {
            $price = $this->shouldVersion($existing)
                ? $this->versionFrom($existing, $attributes)
                : $this->persist($attributes, $existing);

            $this->syncMetricRates($price, $metricRates);
            $this->syncCreditPolicies($price, $creditPolicies);

            return $price->fresh(['metricRates', 'creditPolicies']) ?? $price;
        });
    }

    public function assignToBusiness(
        Business $business,
        BillingPrice $billingPrice,
        ?CarbonImmutable $billingCycleAnchor = null,
        ?CarbonImmutable $nextInvoiceAt = null,
    ): BusinessSubscription {
        /** @var BusinessSubscription $subscription */
        $subscription = $business->subscription()->with('plan')->firstOrCreate(
            [],
            [
                'plan_id' => $billingPrice->plan_id,
                'status' => $billingPrice->plan?->code ?? $business->plan,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
            ],
        );

        $anchor = $billingCycleAnchor
            ?? ($subscription->billing_cycle_anchor_at
                ? CarbonImmutable::parse($subscription->billing_cycle_anchor_at)
                : ($subscription->current_period_start
                    ? CarbonImmutable::parse($subscription->current_period_start)
                    : CarbonImmutable::now()->startOfMonth()));

        $next = $nextInvoiceAt
            ?? ($subscription->next_invoice_at
                ? CarbonImmutable::parse($subscription->next_invoice_at)
                : ($subscription->current_period_end
                    ? CarbonImmutable::parse($subscription->current_period_end)
                    : $this->advanceCycle($anchor, $billingPrice)));

        $subscription->forceFill([
            'plan_id' => $billingPrice->plan_id ?? $subscription->plan_id,
            'billing_price_id' => $billingPrice->id,
            'price_snapshot' => $this->snapshot($billingPrice, $subscription),
            'billing_cycle_anchor_at' => $anchor,
            'next_invoice_at' => $next,
            'lifecycle_status' => $subscription->lifecycle_status
                ?: (($billingPrice->plan?->code ?? $business->plan) === 'trial' ? 'trial' : 'active'),
        ])->save();

        return $subscription->fresh(['plan', 'billingPrice']) ?? $subscription;
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(BillingPrice $billingPrice, BusinessSubscription $subscription): array
    {
        $billingPrice->loadMissing(['plan', 'metricRates', 'creditPolicies']);
        $plan = $subscription->plan ?? $billingPrice->plan;

        return [
            'billing_price_code' => $billingPrice->code,
            'billing_price_version' => $billingPrice->version,
            'currency' => $billingPrice->currency,
            'recurring' => [
                'amount_minor' => $billingPrice->recurring_amount_minor,
                'interval_unit' => $billingPrice->recurring_interval_unit,
                'interval_count' => $billingPrice->recurring_interval_count,
            ],
            'setup_fee' => [
                'amount_minor' => $billingPrice->setup_fee_amount_minor,
                'behavior' => $billingPrice->setup_fee_behavior,
            ],
            'meter_rates' => $billingPrice->metricRates
                ->map(fn (BillingPriceMetricRate $rate): array => Arr::only($rate->toArray(), [
                    'metric',
                    'currency',
                    'billable_unit',
                    'unit_size',
                    'aggregation_strategy',
                    'rounding_mode',
                    'pricing_model',
                    'unit_amount_minor',
                    'free_units',
                    'cap_units',
                    'balance_bucket',
                    'event_filters',
                ]))
                ->values()
                ->all(),
            'credit_policies' => $billingPrice->creditPolicies
                ->map(fn (BillingPriceCreditPolicy $policy): array => Arr::only($policy->toArray(), [
                    'code',
                    'balance_bucket',
                    'currency',
                    'amount_minor',
                    'grant_cadence',
                    'expires_with_period',
                    'carry_forward',
                ]))
                ->values()
                ->all(),
            'entitlements' => [
                'included_quotas' => array_merge($plan?->included_quotas ?? [], $subscription->included_quotas ?? []),
                'feature_flags' => array_merge($plan?->feature_flags ?? [], $subscription->feature_flags ?? []),
            ],
            'provider' => [
                'driver' => $billingPrice->provider_driver,
                'sellable_ref' => $billingPrice->provider_sellable_ref,
                'variant_ref' => $billingPrice->provider_variant_ref,
            ],
        ];
    }

    public function advanceCycle(CarbonImmutable $from, BillingPrice $billingPrice): CarbonImmutable
    {
        $count = max((int) ($billingPrice->recurring_interval_count ?? 1), 1);
        $unit = $billingPrice->recurring_interval_unit ?: 'month';

        return match ($unit) {
            'day' => $from->addDays($count),
            'week' => $from->addWeeks($count),
            'year' => $from->addYears($count),
            default => $from->addMonthsNoOverflow($count),
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function persist(array $attributes, ?BillingPrice $existing = null): BillingPrice
    {
        $price = $existing ?? new BillingPrice([
            'version' => 1,
        ]);

        $price->fill($attributes);
        $price->save();

        return $price;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function versionFrom(BillingPrice $existing, array $attributes): BillingPrice
    {
        $nextVersion = (int) BillingPrice::query()
            ->where('code', $existing->code)
            ->max('version') + 1;

        $price = $existing->replicate([
            'created_at',
            'updated_at',
        ]);

        $price->fill($attributes);
        $price->version = $nextVersion;
        $price->supersedes_billing_price_id = $existing->id;
        $price->save();

        if ($existing->status === 'active') {
            $existing->forceFill(['status' => 'superseded'])->save();
        }

        return $price;
    }

    private function shouldVersion(?BillingPrice $price): bool
    {
        if ($price === null) {
            return false;
        }

        return $price->subscriptions()->exists()
            || BillingDocumentLine::query()->where('billing_price_id', $price->id)->exists();
    }

    /**
     * @param  array<int, array<string, mixed>>  $metricRates
     */
    private function syncMetricRates(BillingPrice $billingPrice, array $metricRates): void
    {
        $billingPrice->metricRates()->delete();

        foreach ($metricRates as $rate) {
            $billingPrice->metricRates()->create([
                'metric' => (string) ($rate['metric'] ?? ''),
                'currency' => (string) ($rate['currency'] ?? $billingPrice->currency ?? 'USD'),
                'billable_unit' => (string) ($rate['billable_unit'] ?? 'unit'),
                'unit_size' => (float) ($rate['unit_size'] ?? 1),
                'aggregation_strategy' => (string) ($rate['aggregation_strategy'] ?? 'sum_quantity'),
                'rounding_mode' => (string) ($rate['rounding_mode'] ?? 'none'),
                'pricing_model' => (string) ($rate['pricing_model'] ?? 'per_unit'),
                'unit_amount_minor' => isset($rate['unit_amount_minor']) ? (int) $rate['unit_amount_minor'] : null,
                'free_units' => isset($rate['free_units']) ? (float) $rate['free_units'] : null,
                'cap_units' => isset($rate['cap_units']) ? (float) $rate['cap_units'] : null,
                'balance_bucket' => filled($rate['balance_bucket'] ?? null) ? (string) $rate['balance_bucket'] : null,
                'event_filters' => is_array($rate['event_filters'] ?? null) ? $rate['event_filters'] : null,
                'metadata' => is_array($rate['metadata'] ?? null) ? $rate['metadata'] : null,
                'is_active' => (bool) ($rate['is_active'] ?? true),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $creditPolicies
     */
    private function syncCreditPolicies(BillingPrice $billingPrice, array $creditPolicies): void
    {
        $billingPrice->creditPolicies()->delete();

        foreach ($creditPolicies as $policy) {
            $billingPrice->creditPolicies()->create([
                'code' => (string) ($policy['code'] ?? ''),
                'balance_bucket' => (string) ($policy['balance_bucket'] ?? 'account_credit'),
                'currency' => (string) ($policy['currency'] ?? $billingPrice->currency ?? 'USD'),
                'amount_minor' => (int) ($policy['amount_minor'] ?? 0),
                'grant_cadence' => (string) ($policy['grant_cadence'] ?? 'per_billing_cycle'),
                'expires_with_period' => (bool) ($policy['expires_with_period'] ?? true),
                'carry_forward' => (bool) ($policy['carry_forward'] ?? false),
                'metadata' => is_array($policy['metadata'] ?? null) ? $policy['metadata'] : null,
                'is_active' => (bool) ($policy['is_active'] ?? true),
            ]);
        }
    }
}
