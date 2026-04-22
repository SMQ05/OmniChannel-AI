<?php

declare(strict_types=1);

namespace App\Services\Conversations;

use App\Events\HumanHandoffRequested;
use App\Models\Business;
use App\Models\ConversationLog;
use App\Models\Patient;
use Illuminate\Support\Facades\Cache;

class HumanHandoffService
{
    private const HUMAN_MODE_PREFIX = 'human_mode';
    private const HUMAN_MODE_TTL_SECONDS = 86400;

    public function activate(
        Business $business,
        Patient $patient,
        ConversationLog $conversationLog,
        string $source = 'system',
        ?string $reason = null,
    ): bool {
        $wasAlreadyActive = (bool) $conversationLog->human_mode;

        if (!$wasAlreadyActive) {
            $conversationLog->flagHumanHandoff();
            $conversationLog->save();
        }

        Cache::put(
            $this->humanModeKey($business->id, $conversationLog->channel, $patient->platform_user_id),
            true,
            self::HUMAN_MODE_TTL_SECONDS,
        );

        if ($wasAlreadyActive) {
            return false;
        }

        event(new HumanHandoffRequested(
            businessId: $business->id,
            conversationLogId: $conversationLog->id,
            patientId: $patient->id,
            patientName: $patient->name ?: 'Unknown',
            channel: $conversationLog->channel,
            source: $source,
            reason: $reason,
        ));

        return true;
    }

    private function humanModeKey(int $businessId, string $channel, string $platformUserId): string
    {
        return implode(':', [self::HUMAN_MODE_PREFIX, $businessId, $channel, $platformUserId]);
    }
}
