<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingDocument;
use App\Models\BillingTransaction;
use Illuminate\Support\Facades\DB;

class BillingPaymentService
{
    public function __construct(
        private readonly BillingLifecycleService $billingLifecycleService,
    ) {}

    public function markPaid(BillingDocument $document, ?int $amountMinor = null, ?string $reference = null): BillingDocument
    {
        return DB::transaction(function () use ($document, $amountMinor, $reference): BillingDocument {
            $document->loadMissing(['business', 'subscription']);

            $payAmount = $amountMinor ?? (int) $document->amount_due_minor;

            /** @var BillingTransaction $transaction */
            $transaction = BillingTransaction::query()->firstOrCreate(
                ['idempotency_key' => sprintf('manual_payment:%d:%d:%s', $document->id, $payAmount, $reference ?? 'paid')],
                [
                    'business_id' => $document->business_id,
                    'billing_account_id' => $document->billing_account_id,
                    'billing_document_id' => $document->id,
                    'business_subscription_id' => $document->business_subscription_id,
                    'type' => 'payment',
                    'status' => 'settled',
                    'direction' => 'inbound',
                    'currency' => $document->currency,
                    'amount_minor' => $payAmount,
                    'provider_driver' => null,
                    'provider_transaction_ref' => $reference,
                    'effective_at' => now(),
                    'settled_at' => now(),
                    'provider_metadata' => ['manual' => true],
                ],
            );

            $paid = (int) $document->amount_paid_minor;

            if ($transaction->wasRecentlyCreated) {
                $paid = min(
                    $paid + (int) $transaction->amount_minor,
                    (int) $document->total_minor - (int) $document->credit_total_minor,
                );
            }

            $due = max((int) $document->total_minor - (int) $document->credit_total_minor - $paid, 0);

            $document->forceFill([
                'amount_paid_minor' => $paid,
                'amount_due_minor' => $due,
                'status' => $due > 0 ? 'partial' : 'paid',
                'paid_at' => $due > 0 ? null : now(),
            ])->save();

            if ($document->subscription !== null) {
                $this->billingLifecycleService->refreshFromDocuments($document->subscription);
            }

            return $document->fresh('transactions') ?? $document;
        });
    }
}
