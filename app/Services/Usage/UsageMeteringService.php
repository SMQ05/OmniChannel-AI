<?php

declare(strict_types=1);

namespace App\Services\Usage;

use App\Models\Business;
use App\Models\UsageEvent;

class UsageMeteringService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function record(
        Business $business,
        string $metric,
        ?string $channel,
        float|int $quantity,
        string $status,
        ?string $referenceType = null,
        ?int $referenceId = null,
        array $meta = [],
    ): UsageEvent {
        return UsageEvent::query()->create([
            'business_id' => $business->id,
            'metric' => $metric,
            'channel' => $channel,
            'quantity' => $quantity,
            'status' => $status,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'meta' => $meta,
            'recorded_at' => now(),
        ]);
    }
}
