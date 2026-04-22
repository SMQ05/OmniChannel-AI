<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingBalanceEntry;
use App\Models\BillingDocument;
use App\Models\BillingPriceCreditPolicy;
use App\Models\Business;
use App\Models\BusinessSubscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BillingLedgerService
{
    /**
     * @return array<string, int>
     */
    public function balancesForBusiness(Business $business): array
    {
        return BillingBalanceEntry::query()
            ->where('business_id', $business->id)
            ->selectRaw("
                balance_bucket,
                SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END) as balance_minor
            ")
            ->groupBy('balance_bucket')
            ->pluck('balance_minor', 'balance_bucket')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();
    }

    /**
     * @param  array<int, array{bucket: string|null, amount_minor: int}>  $bucketCharges
     */
    public function applyDocumentCredits(BillingDocument $document, array $bucketCharges = []): int
    {
        DB::transaction(function () use ($document): void {
            BillingBalanceEntry::query()
                ->where('billing_document_id', $document->id)
                ->where('entry_type', 'document_application')
                ->delete();
        });

        $business = $document->business;
        $subscription = $document->subscription;

        if ($subscription === null) {
            return 0;
        }

        $remaining = 0;
        foreach ($bucketCharges as $charge) {
            $remaining += (int) $charge['amount_minor'];
        }

        $applied = 0;

        foreach ($bucketCharges as $charge) {
            $bucket = $charge['bucket'];
            $amount = (int) $charge['amount_minor'];

            if ($bucket === null || $amount <= 0) {
                continue;
            }

            $applied += $this->applyFromBucket($business, $subscription, $document, $bucket, $amount);
            $remaining -= min($remaining, $amount);
        }

        if ($remaining > 0) {
            $applied += $this->applyFromBucket($business, $subscription, $document, 'account_credit', $remaining);
        }

        return $applied;
    }

    public function grantPolicy(
        BusinessSubscription $subscription,
        BillingPriceCreditPolicy $policy,
        string $entryKey,
        ?BillingDocument $document = null,
    ): BillingBalanceEntry {
        return BillingBalanceEntry::query()->firstOrCreate(
            ['entry_key' => $entryKey],
            [
                'business_id' => $subscription->business_id,
                'billing_account_id' => $subscription->business->billingAccount?->id,
                'business_subscription_id' => $subscription->id,
                'billing_document_id' => $document?->id,
                'entry_type' => 'credit_grant',
                'balance_bucket' => $policy->balance_bucket,
                'direction' => 'credit',
                'currency' => $policy->currency,
                'amount_minor' => (int) $policy->amount_minor,
                'effective_at' => now(),
                'expires_at' => $policy->expires_with_period ? $document?->period_end : null,
                'source_type' => BillingPriceCreditPolicy::class,
                'source_id' => $policy->id,
                'metadata' => [
                    'policy_code' => $policy->code,
                    'grant_cadence' => $policy->grant_cadence,
                ],
            ],
        );
    }

    public function manualAdjustment(
        BusinessSubscription $subscription,
        string $entryType,
        string $bucket,
        int $amountMinor,
        string $reason,
        ?BillingDocument $document = null,
        ?string $entryKey = null,
    ): BillingBalanceEntry {
        $direction = $entryType === 'manual_credit' ? 'credit' : 'debit';

        if ($direction === 'debit' && $this->availableBucketBalance($subscription->business, $bucket) < $amountMinor) {
            throw new RuntimeException('Cannot debit more credit than is currently available in that balance bucket.');
        }

        return BillingBalanceEntry::query()->firstOrCreate([
            'entry_key' => $entryKey ?: sprintf(
                'manual_adjustment:%d:%s:%s:%d:%s',
                $subscription->id,
                $entryType,
                $bucket,
                $amountMinor,
                sha1($reason),
            ),
        ], [
            'business_id' => $subscription->business_id,
            'billing_account_id' => $subscription->business->billingAccount?->id,
            'business_subscription_id' => $subscription->id,
            'billing_document_id' => $document?->id,
            'entry_type' => $entryType,
            'balance_bucket' => $bucket,
            'direction' => $direction,
            'currency' => $subscription->billingPrice?->currency ?? 'USD',
            'amount_minor' => $amountMinor,
            'effective_at' => now(),
            'source_type' => BusinessSubscription::class,
            'source_id' => $subscription->id,
            'metadata' => ['reason' => $reason],
        ]);
    }

    /**
     * @return Collection<int, BillingBalanceEntry>
     */
    public function expirableCredits(Business $business): Collection
    {
        return BillingBalanceEntry::query()
            ->where('business_id', $business->id)
            ->where('direction', 'credit')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get()
            ->filter(fn (BillingBalanceEntry $entry): bool => $this->remainingForGrant($entry) > 0);
    }

    public function expireDueCredits(Business $business): void
    {
        foreach ($this->expirableCredits($business) as $grant) {
            $remaining = $this->remainingForGrant($grant);

            BillingBalanceEntry::query()->firstOrCreate(
                ['entry_key' => 'credit_expiration:' . $grant->id . ':' . optional($grant->expires_at)->timestamp],
                [
                    'business_id' => $grant->business_id,
                    'billing_account_id' => $grant->billing_account_id,
                    'business_subscription_id' => $grant->business_subscription_id,
                    'entry_type' => 'credit_expiration',
                    'balance_bucket' => $grant->balance_bucket,
                    'direction' => 'debit',
                    'currency' => $grant->currency,
                    'amount_minor' => $remaining,
                    'effective_at' => now(),
                    'applies_to_entry_id' => $grant->id,
                    'source_type' => BillingBalanceEntry::class,
                    'source_id' => $grant->id,
                    'metadata' => ['expired_from_entry_id' => $grant->id],
                ],
            );
        }
    }

    public function availableBucketBalance(Business $business, string $bucket): int
    {
        return (int) BillingBalanceEntry::query()
            ->where('business_id', $business->id)
            ->where('balance_bucket', $bucket)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN direction = 'credit' THEN amount_minor ELSE -amount_minor END), 0) as balance_minor
            ")
            ->value('balance_minor');
    }

    private function applyFromBucket(
        Business $business,
        BusinessSubscription $subscription,
        BillingDocument $document,
        string $bucket,
        int $amountMinor,
    ): int {
        $remainingToApply = $amountMinor;
        $applied = 0;

        $grants = BillingBalanceEntry::query()
            ->where('business_id', $business->id)
            ->where('balance_bucket', $bucket)
            ->where('direction', 'credit')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderBy('effective_at')
            ->get();

        foreach ($grants as $grant) {
            $remainingOnGrant = $this->remainingForGrant($grant);

            if ($remainingOnGrant <= 0) {
                continue;
            }

            $applyAmount = min($remainingToApply, $remainingOnGrant);

            BillingBalanceEntry::query()->firstOrCreate(
                ['entry_key' => sprintf('document_application:%d:%d:%d', $document->id, $grant->id, $applyAmount)],
                [
                    'business_id' => $business->id,
                    'billing_account_id' => $business->billingAccount?->id,
                    'business_subscription_id' => $subscription->id,
                    'billing_document_id' => $document->id,
                    'entry_type' => 'document_application',
                    'balance_bucket' => $bucket,
                    'direction' => 'debit',
                    'currency' => $grant->currency,
                    'amount_minor' => $applyAmount,
                    'effective_at' => now(),
                    'applies_to_entry_id' => $grant->id,
                    'source_type' => BillingDocument::class,
                    'source_id' => $document->id,
                    'metadata' => ['applied_from_entry_id' => $grant->id],
                ],
            );

            $remainingToApply -= $applyAmount;
            $applied += $applyAmount;

            if ($remainingToApply <= 0) {
                break;
            }
        }

        return $applied;
    }

    private function remainingForGrant(BillingBalanceEntry $grant): int
    {
        $applied = (int) BillingBalanceEntry::query()
            ->where('applies_to_entry_id', $grant->id)
            ->selectRaw('COALESCE(SUM(amount_minor), 0) as amount_minor')
            ->value('amount_minor');

        return max((int) $grant->amount_minor - $applied, 0);
    }
}
