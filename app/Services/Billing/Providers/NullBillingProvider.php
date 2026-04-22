<?php

declare(strict_types=1);

namespace App\Services\Billing\Providers;

use App\Models\BillingAccount;
use App\Services\Billing\Contracts\BillingProviderInterface;
use RuntimeException;

class NullBillingProvider implements BillingProviderInterface
{
    public function __construct(
        private readonly string $driver = 'null',
    ) {}

    public function key(): string
    {
        return $this->driver;
    }

    public function supportsPortal(BillingAccount $billingAccount): bool
    {
        return false;
    }

    public function createPortalSession(BillingAccount $billingAccount, string $returnUrl, array $context = []): array
    {
        throw new RuntimeException('No billing provider is configured for customer portal access.');
    }
}
