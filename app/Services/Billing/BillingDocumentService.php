<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingDocument;
use App\Models\BillingDocumentLine;
use App\Models\BillingPriceCreditPolicy;
use App\Models\BusinessSubscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingDocumentService
{
    public function __construct(
        private readonly BillingPriceCatalogService $billingPriceCatalogService,
        private readonly BillingLifecycleService $billingLifecycleService,
        private readonly BillingLedgerService $billingLedgerService,
        private readonly UsageRatingService $usageRatingService,
    ) {}

    public function runCycle(BusinessSubscription $subscription): BillingDocument
    {
        $subscription->loadMissing(['business.billingAccount', 'plan', 'billingPrice.metricRates', 'billingPrice.creditPolicies']);
        $billingPrice = $subscription->billingPrice;

        if ($billingPrice === null) {
            throw new RuntimeException('Billing price is not assigned to this subscription.');
        }

        $periodStart = $this->billingLifecycleService->defaultAnchor($subscription);
        $periodEnd = $this->billingLifecycleService->defaultEnd($subscription);
        $documentKey = sprintf(
            'invoice:%d:%d:%s:%s',
            $subscription->business_id,
            $subscription->id,
            $periodStart->toDateString(),
            $periodEnd->toDateString(),
        );

        return DB::transaction(function () use ($subscription, $billingPrice, $periodStart, $periodEnd, $documentKey): BillingDocument {
            $this->billingLedgerService->expireDueCredits($subscription->business);

            /** @var BillingDocument $document */
            $document = BillingDocument::query()->firstOrCreate(
                ['document_key' => $documentKey],
                [
                    'business_id' => $subscription->business_id,
                    'billing_account_id' => $subscription->business->billingAccount?->id,
                    'business_subscription_id' => $subscription->id,
                    'type' => 'invoice',
                    'status' => 'draft',
                    'currency' => $billingPrice->currency,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'source' => 'billing_cycle',
                ],
            );

            $document->forceFill([
                'billing_account_id' => $subscription->business->billingAccount?->id,
                'currency' => $billingPrice->currency,
                'context_snapshot' => [
                    'legacy_status' => $subscription->status,
                    'lifecycle_status' => $subscription->lifecycle_status,
                    'billing_price_code' => $billingPrice->code,
                    'billing_price_version' => $billingPrice->version,
                ],
            ])->save();

            if ($document->status === 'paid') {
                return $document->fresh('lines') ?? $document;
            }

            $sourceKeys = [];

            if ($billingPrice->recurring_amount_minor !== null && (int) $billingPrice->recurring_amount_minor > 0) {
                $sourceKeys[] = $this->upsertLine($document, [
                    'billing_price_id' => $billingPrice->id,
                    'line_type' => 'subscription_base',
                    'description' => $billingPrice->name . ' monthly subscription',
                    'quantity' => 1,
                    'unit_amount_minor' => (int) $billingPrice->recurring_amount_minor,
                    'subtotal_minor' => (int) $billingPrice->recurring_amount_minor,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'source_key' => sprintf('subscription_base:%d:%s', $document->id, $document->document_key),
                    'snapshot' => ['balance_bucket' => null],
                    'display_order' => 10,
                ]);
            }

            if ($billingPrice->setup_fee_amount_minor !== null
                && (int) $billingPrice->setup_fee_amount_minor > 0
                && $subscription->setup_fee_invoiced_at === null) {
                $sourceKeys[] = $this->upsertLine($document, [
                    'billing_price_id' => $billingPrice->id,
                    'line_type' => 'setup_fee',
                    'description' => $billingPrice->name . ' setup fee',
                    'quantity' => 1,
                    'unit_amount_minor' => (int) $billingPrice->setup_fee_amount_minor,
                    'subtotal_minor' => (int) $billingPrice->setup_fee_amount_minor,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'source_key' => sprintf('setup_fee:%d:%s', $document->id, $document->document_key),
                    'snapshot' => ['balance_bucket' => null],
                    'display_order' => 20,
                ]);
            }

            foreach ($this->usageRatingService->rate($subscription, $billingPrice, $periodStart, $periodEnd) as $index => $ratedLine) {
                $sourceKeys[] = $this->upsertLine($document, [
                    'billing_price_id' => $billingPrice->id,
                    'line_type' => 'usage_overage',
                    'metric' => $ratedLine['metric'],
                    'description' => $ratedLine['description'],
                    'quantity' => $ratedLine['quantity'],
                    'unit_amount_minor' => $ratedLine['unit_amount_minor'],
                    'subtotal_minor' => $ratedLine['subtotal_minor'],
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'source_key' => $ratedLine['source_key'],
                    'snapshot' => $ratedLine['snapshot'],
                    'display_order' => 100 + $index,
                ]);
            }

            $document->lines()
                ->whereNotNull('source_key')
                ->whereNotIn('source_key', $sourceKeys)
                ->delete();

            $document->load('lines');

            foreach ($billingPrice->creditPolicies as $policy) {
                if (!$policy->is_active) {
                    continue;
                }

                $this->billingLedgerService->grantPolicy(
                    subscription: $subscription,
                    policy: $policy,
                    entryKey: sprintf(
                        'credit_grant:%d:%d:%s:%s',
                        $subscription->id,
                        $policy->id,
                        $periodStart->toDateString(),
                        $periodEnd->toDateString(),
                    ),
                    document: $document,
                );
            }

            $lineCharges = $document->lines
                ->map(function (BillingDocumentLine $line): array {
                    $bucket = is_array($line->snapshot ?? null) ? ($line->snapshot['balance_bucket'] ?? null) : null;

                    return [
                        'bucket' => is_string($bucket) && $bucket !== '' ? $bucket : null,
                        'amount_minor' => (int) $line->subtotal_minor,
                    ];
                })
                ->all();

            $creditTotal = $this->billingLedgerService->applyDocumentCredits($document, $lineCharges);
            $subtotal = (int) $document->lines()->sum('subtotal_minor');

            $document->forceFill([
                'status' => max($subtotal - $creditTotal - (int) $document->amount_paid_minor, 0) > 0
                    ? ((int) $document->amount_paid_minor > 0 ? 'partial' : 'issued')
                    : 'paid',
                'subtotal_minor' => $subtotal,
                'credit_total_minor' => $creditTotal,
                'tax_total_minor' => 0,
                'total_minor' => $subtotal,
                'amount_due_minor' => max($subtotal - $creditTotal - (int) $document->amount_paid_minor, 0),
                'issued_at' => $document->issued_at ?? now(),
                'finalized_at' => now(),
                'due_at' => $document->due_at ?? now()->addDays(7),
                'paid_at' => max($subtotal - $creditTotal - (int) $document->amount_paid_minor, 0) === 0 ? ($document->paid_at ?? now()) : null,
            ])->save();

            if ($billingPrice->setup_fee_amount_minor !== null
                && (int) $billingPrice->setup_fee_amount_minor > 0
                && $subscription->setup_fee_invoiced_at === null
                && $document->lines()->where('line_type', 'setup_fee')->exists()) {
                $subscription->forceFill([
                    'setup_fee_invoiced_at' => now(),
                ])->save();
            }

            $nextAnchor = $periodEnd;
            $nextInvoice = $this->billingPriceCatalogService->advanceCycle($periodEnd, $billingPrice);

            $subscription->forceFill([
                'billing_cycle_anchor_at' => $nextAnchor,
                'next_invoice_at' => $nextInvoice,
                'price_snapshot' => $this->billingPriceCatalogService->snapshot($billingPrice, $subscription),
            ])->save();

            return $this->billingLifecycleService->refreshFromDocuments($subscription)
                ->billingDocuments()
                ->whereKey($document->id)
                ->with('lines')
                ->firstOrFail();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertLine(BillingDocument $document, array $attributes): string
    {
        $sourceKey = (string) ($attributes['source_key'] ?? '');

        /** @var BillingDocumentLine $line */
        $line = BillingDocumentLine::query()->firstOrNew([
            'source_key' => $sourceKey,
        ]);

        $line->fill(array_merge([
            'billing_document_id' => $document->id,
        ], $attributes));
        $line->save();

        return $sourceKey;
    }
}
