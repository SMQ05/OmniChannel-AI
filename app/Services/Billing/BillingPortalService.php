<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Business;
use RuntimeException;

class BillingPortalService
{
    public function __construct(
        private readonly BillingProviderManager $billingProviderManager,
    ) {}

    public function launch(Business $business, string $returnUrl): string
    {
        $business->loadMissing('billingAccount');

        $account = $business->billingAccount;

        if ($account === null) {
            throw new RuntimeException('No billing account is configured for this business.');
        }

        $provider = $this->billingProviderManager->driver($account->provider_driver);

        if (!$provider->supportsPortal($account)) {
            throw new RuntimeException('Customer portal access is not configured for this billing provider.');
        }

        $session = $provider->createPortalSession($account, $returnUrl, [
            'business_id' => $business->id,
        ]);

        return $session['url'];
    }
}
