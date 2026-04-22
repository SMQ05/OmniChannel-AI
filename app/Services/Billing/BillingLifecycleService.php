<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingAccount;
use App\Models\BillingDocument;
use App\Models\Business;
use App\Models\BusinessSubscription;
use Carbon\CarbonImmutable;

class BillingLifecycleService
{
    public function lifecycleStatus(Business $business): ?string
    {
        return $business->subscription?->lifecycle_status;
    }

    public function blocksOutboundMessaging(Business $business): bool
    {
        return $this->lifecycleStatus($business) === 'suspended';
    }

    public function blocksReminders(Business $business): bool
    {
        return $this->blocksOutboundMessaging($business);
    }

    public function blocksVoiceSessions(Business $business): bool
    {
        return $this->lifecycleStatus($business) === 'suspended';
    }

    public function suspend(BusinessSubscription $subscription, string $reason = ''): BusinessSubscription
    {
        $subscription->forceFill([
            'lifecycle_status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $reason !== '' ? $reason : null,
        ])->save();

        $subscription->business->billingAccount?->forceFill([
            'collection_status' => 'suspended',
        ])->save();

        return $subscription->fresh() ?? $subscription;
    }

    public function reactivate(BusinessSubscription $subscription): BusinessSubscription
    {
        $nextStatus = $subscription->plan?->code === 'trial' || $subscription->business->plan === 'trial'
            ? 'trial'
            : 'active';

        $subscription->forceFill([
            'lifecycle_status' => $nextStatus,
            'reactivated_at' => now(),
            'past_due_at' => null,
            'suspension_reason' => null,
        ])->save();

        $subscription->business->billingAccount?->forceFill([
            'collection_status' => 'active',
        ])->save();

        return $subscription->fresh() ?? $subscription;
    }

    public function refreshFromDocuments(BusinessSubscription $subscription): BusinessSubscription
    {
        if ($subscription->lifecycle_status === 'suspended') {
            return $subscription;
        }

        $baseStatus = $subscription->plan?->code === 'trial' || $subscription->business->plan === 'trial'
            ? 'trial'
            : 'active';

        $hasPastDue = $subscription->billingDocuments()
            ->whereIn('status', ['issued', 'partial'])
            ->where('amount_due_minor', '>', 0)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->exists();

        $subscription->forceFill([
            'lifecycle_status' => $hasPastDue ? 'past_due' : $baseStatus,
            'past_due_at' => $hasPastDue ? ($subscription->past_due_at ?? now()) : null,
        ])->save();

        $subscription->business->billingAccount?->forceFill([
            'collection_status' => $hasPastDue ? 'past_due' : 'active',
        ])->save();

        return $subscription->fresh() ?? $subscription;
    }

    public function defaultAnchor(BusinessSubscription $subscription): CarbonImmutable
    {
        return $subscription->billing_cycle_anchor_at
            ? CarbonImmutable::parse($subscription->billing_cycle_anchor_at)
            : ($subscription->current_period_start
                ? CarbonImmutable::parse($subscription->current_period_start)
                : CarbonImmutable::now()->startOfMonth());
    }

    public function defaultEnd(BusinessSubscription $subscription): CarbonImmutable
    {
        return $subscription->next_invoice_at
            ? CarbonImmutable::parse($subscription->next_invoice_at)
            : ($subscription->current_period_end
                ? CarbonImmutable::parse($subscription->current_period_end)
                : CarbonImmutable::now()->endOfMonth());
    }
}
