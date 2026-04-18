<?php

declare(strict_types=1);

namespace App\Services\Usage;

use App\Models\Business;
use App\Models\Plan;
use App\Models\UsageEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class UsageSummaryService
{
    /**
     * @return array<string, string>
     */
    public function metricsCatalog(): array
    {
        return [
            'messages_received' => 'Inbound Messages',
            'messages_sent' => 'Outbound Messages',
            'reminders_sent' => 'Reminders Sent',
            'llm_tokens_estimated' => 'Estimated AI Tokens',
            'voice_minutes' => 'Voice Minutes',
            'failed_sends' => 'Failed / Retried Sends',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forBusiness(Business $business): array
    {
        $subscription = $business->subscription?->loadMissing('plan');
        $plan = $subscription?->plan;

        $periodStart = $subscription?->current_period_start
            ? CarbonImmutable::parse($subscription->current_period_start)
            : CarbonImmutable::now()->startOfMonth();
        $periodEnd = $subscription?->current_period_end
            ? CarbonImmutable::parse($subscription->current_period_end)
            : CarbonImmutable::now()->endOfMonth();

        $quotas = $this->mergedQuotas($business);
        $featureFlags = $this->mergedFeatureFlags($business);
        $totals = $this->usageTotals($business, $periodStart);
        $rows = [];

        foreach ($this->metricsCatalog() as $metric => $label) {
            $used = (float) ($totals[$metric] ?? 0);
            $limit = isset($quotas[$metric]) ? (float) $quotas[$metric] : null;
            $warningThreshold = (float) ($subscription?->warn_at_ratio ?? config('kynex.usage.warning_threshold'));
            $ratio = $limit !== null && $limit > 0 ? min($used / $limit, 1.0) : null;

            $rows[] = [
                'metric' => $metric,
                'label' => $label,
                'used' => $used,
                'limit' => $limit,
                'remaining' => $limit !== null ? max($limit - $used, 0) : null,
                'ratio' => $ratio,
                'warning' => $ratio !== null && $ratio >= $warningThreshold,
                'allowed' => $limit === null || !$subscription?->enforce_limits || $used < $limit || $subscription?->admin_override,
            ];
        }

        /** @var Collection<int, UsageEvent> $latestEvents */
        $latestEvents = UsageEvent::query()
            ->where('business_id', $business->id)
            ->where('recorded_at', '>=', $periodStart)
            ->latest('recorded_at')
            ->limit(10)
            ->get();

        return [
            'plan' => [
                'code' => $plan?->code ?? $business->plan,
                'name' => $plan?->name ?? ucfirst($business->plan),
                'description' => $plan?->description,
                'status' => $subscription?->status ?? $business->plan,
            ],
            'period' => [
                'start' => $periodStart,
                'end' => $periodEnd,
            ],
            'controls' => [
                'warn_at_ratio' => (float) ($subscription?->warn_at_ratio ?? config('kynex.usage.warning_threshold')),
                'enforce_limits' => (bool) ($subscription?->enforce_limits ?? false),
                'admin_override' => (bool) ($subscription?->admin_override ?? false),
            ],
            'quotas' => $quotas,
            'feature_flags' => $featureFlags,
            'metrics' => $rows,
            'latest_events' => $latestEvents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedQuotas(Business $business): array
    {
        $subscription = $business->subscription?->loadMissing('plan');
        $plan = $subscription?->plan;

        return array_merge(
            $plan instanceof Plan ? ($plan->included_quotas ?? []) : [],
            $subscription?->included_quotas ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedFeatureFlags(Business $business): array
    {
        $subscription = $business->subscription?->loadMissing('plan');
        $plan = $subscription?->plan;

        return array_merge(
            $plan instanceof Plan ? ($plan->feature_flags ?? []) : [],
            $subscription?->feature_flags ?? [],
        );
    }

    /**
     * @return array<string, float>
     */
    private function usageTotals(Business $business, CarbonImmutable $periodStart): array
    {
        return UsageEvent::query()
            ->where('business_id', $business->id)
            ->where('recorded_at', '>=', $periodStart)
            ->selectRaw('metric, SUM(quantity) as aggregate')
            ->groupBy('metric')
            ->pluck('aggregate', 'metric')
            ->map(static fn ($value): float => (float) $value)
            ->all();
    }
}
