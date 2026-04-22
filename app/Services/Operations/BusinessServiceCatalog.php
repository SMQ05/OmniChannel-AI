<?php

declare(strict_types=1);

namespace App\Services\Operations;

use App\Models\Business;
use App\Models\BusinessService;
use App\Models\Provider;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BusinessServiceCatalog
{
    /**
     * @return Collection<int, BusinessService>
     */
    public function activeForBusiness(Business $business): Collection
    {
        return BusinessService::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->with('providers:id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function hasStructuredServices(Business $business): bool
    {
        return BusinessService::query()
            ->where('business_id', $business->id)
            ->where('is_active', true)
            ->exists();
    }

    public function findForBusiness(Business $business, ?int $serviceId): ?BusinessService
    {
        if ($serviceId === null || $serviceId <= 0) {
            return null;
        }

        return BusinessService::query()
            ->where('business_id', $business->id)
            ->with('providers:id')
            ->find($serviceId);
    }

    public function resolveForBusiness(Business $business, ?int $serviceId, ?string $serviceType): ?BusinessService
    {
        $byId = $this->findForBusiness($business, $serviceId);

        if ($byId !== null) {
            return $byId;
        }

        $serviceName = trim((string) $serviceType);

        if ($serviceName === '') {
            return null;
        }

        return BusinessService::query()
            ->where('business_id', $business->id)
            ->with('providers:id')
            ->where(function ($query) use ($serviceName): void {
                $query
                    ->whereRaw('LOWER(name) = ?', [Str::lower($serviceName)])
                    ->orWhere('slug', Str::slug($serviceName));
            })
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->first();
    }

    /**
     * @return list<array{name: string, duration_min: int, price?: float, id?: int}>
     */
    public function promptCatalog(Business $business): array
    {
        $structured = $this->activeForBusiness($business);

        if ($structured->isNotEmpty()) {
            return $structured
                ->map(fn (BusinessService $service): array => $service->toLegacyPromptShape())
                ->values()
                ->all();
        }

        $legacy = $business->ai_config['services'] ?? [];

        return is_array($legacy) ? $legacy : [];
    }

    public function providerCanDeliver(Provider $provider, ?BusinessService $service): bool
    {
        if ($service === null) {
            return true;
        }

        if (!$provider->relationLoaded('services')) {
            $provider->load('services:id');
        }

        return $provider->services->contains('id', $service->id);
    }
}
