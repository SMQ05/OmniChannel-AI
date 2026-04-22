<?php

declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class HumanHandoffRequested implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly string $requestedAt;

    public function __construct(
        public readonly int $businessId,
        public readonly int $conversationLogId,
        public readonly int $patientId,
        public readonly string $patientName,
        public readonly string $channel,
        public readonly string $source = 'system',
        public readonly ?string $reason = null,
    ) {
        $this->requestedAt = now()->utc()->toISOString();
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("business.{$this->businessId}.handoffs")];
    }

    public function broadcastAs(): string
    {
        return 'handoff.requested';
    }

    /**
     * @return array<string, int|string|null>
     */
    public function broadcastWith(): array
    {
        return [
            'business_id' => $this->businessId,
            'conversation_log_id' => $this->conversationLogId,
            'patient_id' => $this->patientId,
            'patient_name' => $this->patientName,
            'channel' => $this->channel,
            'source' => $this->source,
            'reason' => $this->reason,
            'requested_at' => $this->requestedAt,
        ];
    }
}
