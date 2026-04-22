<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Queue\Attributes\Backoff;
use App\Queue\Attributes\Tries;
use App\Queue\Concerns\InteractsWithQueueAttributes;
use App\Services\DataControls\GovernanceWorkflowService;
use App\Services\Queue\QueueRouteResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

#[Tries(3)]
#[Backoff(120)]
class ProcessDataGovernanceRequestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use InteractsWithQueueAttributes;

    public function __construct(
        private readonly int $requestId,
        private readonly ?int $actorUserId = null,
    ) {
        $this->initQueueAttributes();
        app(QueueRouteResolver::class)->apply($this, 'integrations');
    }

    public function handle(GovernanceWorkflowService $workflowService): void
    {
        $workflowService->executeApprovedRequest($this->requestId, $this->actorUserId);
    }
}
