<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Business;
use App\Queue\Attributes\Backoff;
use App\Queue\Attributes\Tries;
use App\Queue\Concerns\InteractsWithQueueAttributes;
use App\Services\Monitoring\MonitoringSnapshotService;
use App\Services\Queue\QueueRouteResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Tries(3)]
#[Backoff(60)]
class CaptureMonitoringSnapshotJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use InteractsWithQueueAttributes;

    public function __construct(
        private readonly ?int $businessId = null,
    ) {
        $this->initQueueAttributes();
        app(QueueRouteResolver::class)->apply($this, 'integrations');
    }

    public function handle(MonitoringSnapshotService $snapshotService): void
    {
        if ($this->businessId === null) {
            $snapshotService->capturePlatformSnapshot();

            return;
        }

        $business = Business::query()->find($this->businessId);
        if ($business === null) {
            return;
        }

        $snapshotService->captureTenantSnapshot($business);
    }
}
