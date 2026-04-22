<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingDocument;
use App\Models\Business;
use Carbon\CarbonImmutable;

class BillingSummaryService
{
    public function __construct(
        private readonly BillingLedgerService $billingLedgerService,
        private readonly UsageRatingService $usageRatingService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forBusiness(Business $business): array
    {
        $business->loadMissing([
            'billingAccount',
            'subscription.plan',
            'subscription.billingPrice.metricRates',
            'subscription.billingPrice.creditPolicies',
            'billingDocuments',
        ]);

        $subscription = $business->subscription;
        $billingPrice = $subscription?->billingPrice;
        $documents = $business->billingDocuments()
            ->latest('issued_at')
            ->latest('created_at')
            ->limit(10)
            ->get();

        $openBalance = (int) $business->billingDocuments()
            ->whereIn('status', ['issued', 'partial'])
            ->sum('amount_due_minor');

        $estimates = [];
        $estimateTotal = 0;

        if ($subscription !== null && $billingPrice !== null) {
            $now = CarbonImmutable::now();
            $periodStart = $subscription->billing_cycle_anchor_at
                ? CarbonImmutable::parse($subscription->billing_cycle_anchor_at)
                : ($subscription->current_period_start
                    ? CarbonImmutable::parse($subscription->current_period_start)
                    : CarbonImmutable::now()->startOfMonth());

            $periodEnd = $subscription->next_invoice_at
                ? CarbonImmutable::parse($subscription->next_invoice_at)
                : CarbonImmutable::now()->endOfMonth();

            $estimateWindowEnd = $periodEnd->lessThan($now) ? $periodEnd : $now;
            $estimates = $this->usageRatingService->rate($subscription, $billingPrice, $periodStart, $estimateWindowEnd);
            $estimateTotal = array_sum(array_map(static fn (array $line): int => (int) $line['subtotal_minor'], $estimates));
        }

        return [
            'subscription' => [
                'legacy_status' => $subscription?->status,
                'lifecycle_status' => $subscription?->lifecycle_status,
                'plan_name' => $subscription?->plan?->name ?? ucfirst($business->plan),
                'billing_price' => $billingPrice?->name,
                'billing_price_code' => $billingPrice?->code,
                'billing_price_version' => $billingPrice?->version,
                'currency' => $billingPrice?->currency ?? $business->billingAccount?->currency ?? 'USD',
                'recurring_amount_minor' => $billingPrice?->recurring_amount_minor,
                'next_invoice_at' => $subscription?->next_invoice_at,
                'billing_cycle_anchor_at' => $subscription?->billing_cycle_anchor_at,
                'legacy_status_note' => 'Legacy compatibility state only. Billing truth lives in lifecycle status, billing documents, and the ledger.',
            ],
            'account' => $business->billingAccount,
            'documents' => $documents,
            'open_balance_minor' => $openBalance,
            'balances' => $this->billingLedgerService->balancesForBusiness($business),
            'estimated_overages' => [
                'total_minor' => $estimateTotal,
                'lines' => $estimates,
            ],
            'architecture_note' => 'Billing truth is managed here. Messaging, voice, webhook, and runtime controls are still split across separate surfaces.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forAdminBusiness(Business $business): array
    {
        $summary = $this->forBusiness($business);

        $summary['document_totals'] = [
            'issued' => $business->billingDocuments()->whereIn('status', ['issued', 'partial'])->count(),
            'paid' => $business->billingDocuments()->where('status', 'paid')->count(),
            'total_billed_minor' => (int) $business->billingDocuments()->sum('total_minor'),
        ];

        return $summary;
    }
}
