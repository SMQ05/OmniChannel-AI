<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BusinessSubscription;
use App\Queue\Attributes\Backoff;
use App\Queue\Attributes\Tries;
use App\Queue\Concerns\InteractsWithQueueAttributes;
use App\Services\Billing\BillingDocumentService;
use App\Services\Queue\QueueRouteResolver;
use App\Services\Queue\WorkerHeartbeatService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

#[Tries(3)]
#[Backoff(120)]
class RunBillingCycleForSubscriptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use InteractsWithQueueAttributes;

    public function __construct(
        private readonly int $subscriptionId,
    ) {
        $this->initQueueAttributes();
        app(QueueRouteResolver::class)->apply($this, 'billing');
    }

    public function handle(
        BillingDocumentService $billingDocumentService,
        WorkerHeartbeatService $workerHeartbeatService,
    ): void {
        $workerHeartbeatService->beat(
            queueConnection: $this->connection ?: (string) config('queue.default'),
            queueName: $this->queue ?: 'default',
            meta: ['job' => self::class, 'subscription_id' => $this->subscriptionId],
        );

        $subscription = BusinessSubscription::query()->with(['business.billingAccount', 'billingPrice'])->find($this->subscriptionId);

        if ($subscription === null || $subscription->billing_price_id === null) {
            return;
        }

        if (in_array($subscription->lifecycle_status, ['suspended', 'canceled', 'expired'], true)) {
            Log::info('RunBillingCycleForSubscriptionJob skipped for inactive billing lifecycle.', [
                'subscription_id' => $subscription->id,
                'lifecycle_status' => $subscription->lifecycle_status,
            ]);

            return;
        }

        $billingDocumentService->runCycle($subscription);
    }
}
