<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Services\Billing\Contracts\BillingProviderInterface;
use App\Services\Billing\Providers\NullBillingProvider;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

class BillingProviderManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    public function driver(?string $driver = null): BillingProviderInterface
    {
        $driver ??= config('services.billing.default_driver');

        if ($driver === null || $driver === '') {
            return new NullBillingProvider();
        }

        $config = config("services.billing.providers.{$driver}");

        if (!is_array($config) || !isset($config['class']) || !is_string($config['class'])) {
            return new NullBillingProvider($driver);
        }

        $provider = $this->container->make($config['class'], [
            'driver' => $driver,
            'config' => $config,
        ]);

        if (!$provider instanceof BillingProviderInterface) {
            throw new RuntimeException("Billing provider [{$driver}] must implement BillingProviderInterface.");
        }

        return $provider;
    }
}
