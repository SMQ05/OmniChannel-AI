<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\BillingAccount;
use App\Models\Business;

class BillingAccountService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function ensure(Business $business, array $attributes = []): BillingAccount
    {
        /** @var BillingAccount $account */
        $account = $business->billingAccount()->firstOrNew();

        $account->fill(array_merge([
            'currency' => 'USD',
            'collection_status' => 'unconfigured',
            'default_payment_state' => 'unknown',
            'portal_capable' => false,
        ], $attributes));

        $account->business()->associate($business);
        $account->save();

        return $account;
    }
}
