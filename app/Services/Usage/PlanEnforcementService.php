<?php

declare(strict_types=1);

namespace App\Services\Usage;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\UsageEvent;

class PlanEnforcementService
{
    /**
     * @return array{allowed: bool, warning: bool, limit: int|null, used: int}
     */
    public function assess(Business $business, string $metric): array
    {
        /** @var BusinessSubscription|null $subscription */
        $subscription = $business->subscription;
        $plan = $subscription?->plan;

        $quotas = array_merge(
            $plan instanceof Plan ? ($plan->included_quotas ?? []) : [],
            $subscription?->included_quotas ?? [],
        );

        $limit = isset($quotas[$metric]) ? (int) $quotas[$metric] : null;
        $used = (int) UsageEvent::query()
            ->where('business_id', $business->id)
            ->where('metric', $metric)
            ->when(
                $subscription?->current_period_start,
                fn ($query) => $query->where('recorded_at', '>=', $subscription->current_period_start),
            )
            ->sum('quantity');

        if ($limit === null || $limit <= 0 || $subscription?->admin_override) {
            return ['allowed' => true, 'warning' => false, 'limit' => $limit, 'used' => $used];
        }

        $warningThreshold = (float) ($subscription?->warn_at_ratio ?? config('kynex.usage.warning_threshold'));
        $warning = $used >= (int) floor($limit * $warningThreshold);
        $allowed = !$subscription->enforce_limits || $used < $limit;

        return ['allowed' => $allowed, 'warning' => $warning, 'limit' => $limit, 'used' => $used];
    }
}
