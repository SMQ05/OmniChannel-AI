<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingPrice;
use App\Models\BillingPriceMetricRate;
use App\Models\BusinessSubscription;
use App\Models\UsageEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class UsageRatingService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function rate(
        BusinessSubscription $subscription,
        BillingPrice $billingPrice,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): array {
        $billingPrice->loadMissing('metricRates');

        $lines = [];

        foreach ($billingPrice->metricRates as $rate) {
            if (!$rate->is_active) {
                continue;
            }

            $aggregate = $this->aggregate($subscription, $rate, $periodStart, $periodEnd);
            $baseUnits = $this->baseUnits($aggregate, (float) $rate->unit_size, $rate->rounding_mode);
            $freeUnits = max((float) ($rate->free_units ?? 0), 0.0);
            $billableUnits = max($baseUnits - $freeUnits, 0.0);

            if ($rate->cap_units !== null) {
                $billableUnits = min($billableUnits, (float) $rate->cap_units);
            }

            $subtotalMinor = (int) round($billableUnits * (int) ($rate->unit_amount_minor ?? 0));

            if ($subtotalMinor <= 0 && $billableUnits <= 0) {
                continue;
            }

            $lines[] = [
                'metric' => $rate->metric,
                'description' => ucfirst(str_replace('_', ' ', $rate->metric)) . ' overage',
                'quantity' => $billableUnits,
                'unit_amount_minor' => (int) ($rate->unit_amount_minor ?? 0),
                'subtotal_minor' => $subtotalMinor,
                'balance_bucket' => $rate->balance_bucket,
                'pricing_model' => $rate->pricing_model,
                'source_key' => sha1(json_encode([
                    'subscription_id' => $subscription->id,
                    'billing_price_id' => $billingPrice->id,
                    'metric' => $rate->metric,
                    'period_start' => $periodStart->toISOString(),
                    'period_end' => $periodEnd->toISOString(),
                    'filters' => $rate->event_filters,
                    'pricing_model' => $rate->pricing_model,
                    'rounding_mode' => $rate->rounding_mode,
                    'free_units' => $rate->free_units,
                    'unit_amount_minor' => $rate->unit_amount_minor,
                ], JSON_THROW_ON_ERROR)),
                'snapshot' => [
                    'metric' => $rate->metric,
                    'currency' => $rate->currency,
                    'billable_unit' => $rate->billable_unit,
                    'unit_size' => (float) $rate->unit_size,
                    'aggregation_strategy' => $rate->aggregation_strategy,
                    'rounding_mode' => $rate->rounding_mode,
                    'pricing_model' => $rate->pricing_model,
                    'balance_bucket' => $rate->balance_bucket,
                    'aggregate' => $aggregate,
                    'free_units' => $freeUnits,
                    'billable_units' => $billableUnits,
                    'event_filters' => $rate->event_filters,
                ],
            ];
        }

        return $lines;
    }

    private function aggregate(
        BusinessSubscription $subscription,
        BillingPriceMetricRate $rate,
        CarbonImmutable $periodStart,
        CarbonImmutable $periodEnd,
    ): float {
        $query = UsageEvent::query()
            ->where('business_id', $subscription->business_id)
            ->where('metric', $rate->metric)
            ->where('recorded_at', '>=', $periodStart)
            ->where('recorded_at', '<', $periodEnd);

        $filters = is_array($rate->event_filters) ? $rate->event_filters : [];
        $this->applyFilters($query, $filters);

        return match ($rate->aggregation_strategy) {
            'count_events' => (float) $query->count(),
            'max_quantity' => (float) ($query->max('quantity') ?? 0),
            default => (float) $query->sum('quantity'),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (!empty($filters['status']) && is_array($filters['status'])) {
            $query->whereIn('status', array_map('strval', $filters['status']));
        }

        if (!empty($filters['channel']) && is_array($filters['channel'])) {
            $query->whereIn('channel', array_map('strval', $filters['channel']));
        }
    }

    private function baseUnits(float $aggregate, float $unitSize, string $roundingMode): float
    {
        $unitSize = $unitSize > 0 ? $unitSize : 1.0;
        $value = $aggregate / $unitSize;

        return match ($roundingMode) {
            'up' => (float) ceil($value),
            'down' => (float) floor($value),
            'half_up' => round($value, 0, PHP_ROUND_HALF_UP),
            default => $value,
        };
    }
}
