<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function log(
        ?User $actor,
        string $action,
        string $subjectType,
        int|string|null $subjectId = null,
        array $payload = [],
        ?Request $request = null,
        ?int $businessId = null,
    ): AuditLog {
        $requestId = $request?->headers->get('X-Request-Id')
            ?: (is_string($payload['request_id'] ?? null) ? $payload['request_id'] : null)
            ?: (string) Str::uuid();

        return AuditLog::query()->create([
            'business_id' => $businessId ?? $actor?->business_id,
            'actor_user_id' => $actor?->id,
            'actor_role' => $actor?->role,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => is_numeric((string) $subjectId) ? (int) $subjectId : null,
            'request_id' => $requestId,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}
