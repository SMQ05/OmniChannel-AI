<?php

declare(strict_types=1);

namespace App\Services\Billing\Contracts;

use App\Models\BillingAccount;

interface BillingProviderInterface
{
    public function key(): string;

    public function supportsPortal(BillingAccount $billingAccount): bool;

    /**
     * @param  array<string, mixed>  $context
     * @return array{url: string}
     */
    public function createPortalSession(BillingAccount $billingAccount, string $returnUrl, array $context = []): array;
}
