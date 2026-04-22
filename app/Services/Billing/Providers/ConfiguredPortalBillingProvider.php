<?php

declare(strict_types=1);

namespace App\Services\Billing\Providers;

use App\Models\BillingAccount;
use App\Services\Billing\Contracts\BillingProviderInterface;
use RuntimeException;

class ConfiguredPortalBillingProvider implements BillingProviderInterface
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly string $driver,
        private readonly array $config = [],
    ) {}

    public function key(): string
    {
        return $this->driver;
    }

    public function supportsPortal(BillingAccount $billingAccount): bool
    {
        return $this->portalTemplate($billingAccount) !== null;
    }

    public function createPortalSession(BillingAccount $billingAccount, string $returnUrl, array $context = []): array
    {
        $template = $this->portalTemplate($billingAccount);

        if ($template === null) {
            throw new RuntimeException("Billing provider [{$this->driver}] does not support portal sessions.");
        }

        $url = str_contains($template, '{')
            ? strtr($template, [
                '{account_ref}' => urlencode((string) ($billingAccount->provider_account_ref ?? '')),
                '{business_id}' => (string) $billingAccount->business_id,
                '{return_url}' => urlencode($returnUrl),
            ])
            : $template;

        if (!str_contains($template, '{return_url}')) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query(array_filter([
                'account_ref' => $billingAccount->provider_account_ref,
                'business_id' => $billingAccount->business_id,
                'return_url' => $returnUrl,
            ], static fn (mixed $value): bool => filled($value)));
        }

        return ['url' => $url];
    }

    private function portalTemplate(BillingAccount $billingAccount): ?string
    {
        $metadata = $billingAccount->provider_metadata ?? [];
        $template = $metadata['portal_url'] ?? $this->config['portal_url'] ?? null;

        if (!is_string($template) || trim($template) === '') {
            return null;
        }

        if (!str_starts_with($template, 'http://') && !str_starts_with($template, 'https://')) {
            throw new RuntimeException("Billing provider [{$this->driver}] portal URL must be absolute.");
        }

        return $template;
    }
}
