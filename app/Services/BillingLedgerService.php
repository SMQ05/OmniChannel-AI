<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\BillingAccount;

/**
 * BillingLedgerService - Manages billing ledger operations.
 *
 * Provides billing-related readiness checks for launch readiness.
 */
class BillingLedgerService
{
    /**
     * Get billing readiness status for a business.
     *
     * @param  Business  $business
     * @return array<string, mixed>
     */
    public function forBusiness(Business $business): array
    {
        $billingAccount = $business->billingAccount ?? null;

        if ($billingAccount === null) {
            return [
                'status' => 'pending',
                'error' => 'No billing account configured',
                'score' => 0,
            ];
        }

        // Check provider configuration
        $hasProvider = $billingAccount->provider_driver !== null;
        $hasAccountRef = $billingAccount->provider_account_ref !== null;

        $score = 0;
        if ($hasProvider) {
            $score += 50;
        }
        if ($hasAccountRef) {
            $score += 50;
        }

        $status = $hasProvider ? 'configured' : 'pending';
        if ($score === 100) {
            $status = 'ready';
        }

        return [
            'provider' => $billingAccount->provider_driver ?? null,
            'has_provider' => $hasProvider,
            'has_account_ref' => $hasAccountRef,
            'status' => $status,
            'score' => $score,
        ];
    }
}
