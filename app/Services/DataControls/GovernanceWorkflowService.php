<?php

declare(strict_types=1);

namespace App\Services\DataControls;

use App\Jobs\ProcessDataGovernanceRequestJob;
use App\Models\Business;
use App\Models\BusinessDataRetentionSetting;
use App\Models\DataGovernanceRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GovernanceWorkflowService
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function requestExport(User $actor, Business $business, array $filters = [], ?string $reason = null): DataGovernanceRequest
    {
        $request = DataGovernanceRequest::query()->create([
            'business_id' => $business->id,
            'request_scope' => 'tenant',
            'request_type' => DataGovernanceRequest::TYPE_EXPORT,
            'status' => DataGovernanceRequest::STATUS_PENDING_APPROVAL,
            'requested_by_user_id' => $actor->id,
            'request_reason' => $reason,
            'requested_filters' => $filters,
            'requested_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $actor,
            action: 'governance.request_export',
            subjectType: DataGovernanceRequest::class,
            subjectId: $request->id,
            payload: ['business_id' => $business->id],
            request: null,
            businessId: $business->id,
        );

        return $request;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function requestDeletion(User $actor, Business $business, array $filters = [], ?string $reason = null): DataGovernanceRequest
    {
        $request = DataGovernanceRequest::query()->create([
            'business_id' => $business->id,
            'request_scope' => 'tenant',
            'request_type' => DataGovernanceRequest::TYPE_DELETE,
            'status' => DataGovernanceRequest::STATUS_PENDING_APPROVAL,
            'requested_by_user_id' => $actor->id,
            'request_reason' => $reason,
            'requested_filters' => $filters,
            'requested_at' => now(),
        ]);

        $this->auditLogger->log(
            actor: $actor,
            action: 'governance.request_delete',
            subjectType: DataGovernanceRequest::class,
            subjectId: $request->id,
            payload: ['business_id' => $business->id, 'mode' => $filters['mode'] ?? 'anonymize'],
            request: null,
            businessId: $business->id,
        );

        return $request;
    }

    public function approve(User $actor, DataGovernanceRequest $request, ?string $reason = null): DataGovernanceRequest
    {
        $policy = $this->resolveDeletionPolicy($request->business_id);

        $request->forceFill([
            'status' => DataGovernanceRequest::STATUS_APPROVED,
            'approved_by_user_id' => $actor->id,
            'approval_reason' => $reason,
            'approved_at' => now(),
            'execution_policy' => $policy,
        ])->save();

        $this->auditLogger->log(
            actor: $actor,
            action: 'governance.approve',
            subjectType: DataGovernanceRequest::class,
            subjectId: $request->id,
            payload: ['request_type' => $request->request_type, 'business_id' => $request->business_id],
            request: null,
            businessId: $request->business_id,
        );

        return $request->refresh();
    }

    public function reject(User $actor, DataGovernanceRequest $request, string $reason): DataGovernanceRequest
    {
        $request->forceFill([
            'status' => DataGovernanceRequest::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by_user_id' => $actor->id,
            'approved_at' => now(),
        ])->save();

        $this->auditLogger->log(
            actor: $actor,
            action: 'governance.reject',
            subjectType: DataGovernanceRequest::class,
            subjectId: $request->id,
            payload: ['request_type' => $request->request_type, 'reason' => $reason],
            request: null,
            businessId: $request->business_id,
        );

        return $request->refresh();
    }

    public function dispatchExecution(DataGovernanceRequest $request, ?User $actor = null): void
    {
        if (!in_array($request->status, [DataGovernanceRequest::STATUS_APPROVED, DataGovernanceRequest::STATUS_FAILED], true)) {
            return;
        }

        ProcessDataGovernanceRequestJob::dispatch($request->id, $actor?->id);
    }

    public function executeApprovedRequest(int $requestId, ?int $actorUserId = null): DataGovernanceRequest
    {
        $actor = $actorUserId !== null ? User::query()->find($actorUserId) : null;
        $shouldExecute = false;

        $request = DB::transaction(function () use ($requestId, $actor, &$shouldExecute): DataGovernanceRequest {
            $request = DataGovernanceRequest::query()->lockForUpdate()->findOrFail($requestId);

            if (!in_array($request->status, [DataGovernanceRequest::STATUS_APPROVED, DataGovernanceRequest::STATUS_RUNNING], true)) {
                return $request;
            }

            if ($request->status === DataGovernanceRequest::STATUS_RUNNING && $request->run_token !== null) {
                return $request;
            }

            $request->forceFill([
                'status' => DataGovernanceRequest::STATUS_RUNNING,
                'started_at' => now(),
                'run_token' => $request->run_token ?? (string) Str::uuid(),
                'executed_by_user_id' => $actor?->id,
            ])->save();
            $shouldExecute = true;

            return $request;
        });

        if (!$shouldExecute) {
            return $request;
        }

        try {
            $result = $request->isExport()
                ? $this->executeExport($request)
                : $this->executeDeletion($request);

            $request->forceFill([
                'status' => DataGovernanceRequest::STATUS_COMPLETED,
                'result_summary' => $result['summary'],
                'artifact_disk' => $result['artifact_disk'] ?? null,
                'artifact_path' => $result['artifact_path'] ?? null,
                'artifact_expires_at' => $result['artifact_expires_at'] ?? null,
                'legal_hold_applied' => (bool) ($result['legal_hold_applied'] ?? false),
                'completed_at' => now(),
            ])->save();

            $this->auditLogger->log(
                actor: $actor,
                action: 'governance.execute_completed',
                subjectType: DataGovernanceRequest::class,
                subjectId: $request->id,
                payload: ['request_type' => $request->request_type, 'summary' => $result['summary']],
                request: null,
                businessId: $request->business_id,
            );
        } catch (Throwable $exception) {
            $request->forceFill([
                'status' => DataGovernanceRequest::STATUS_FAILED,
                'result_summary' => ['error' => $exception->getMessage()],
                'completed_at' => now(),
            ])->save();

            $this->auditLogger->log(
                actor: $actor,
                action: 'governance.execute_failed',
                subjectType: DataGovernanceRequest::class,
                subjectId: $request->id,
                payload: ['error' => $exception->getMessage()],
                request: null,
                businessId: $request->business_id,
            );

            throw $exception;
        }

        return $request->refresh();
    }

    /**
     * @return array{summary: array<string, mixed>, artifact_disk: string, artifact_path: string, artifact_expires_at: \Carbon\CarbonInterface}
     */
    private function executeExport(DataGovernanceRequest $request): array
    {
        $businessId = $request->business_id;
        abort_if($businessId === null, 422, 'Business-scoped export request is required.');

        $disk = (string) config('data_controls.exports.disk', 'local');
        $prefix = trim((string) config('data_controls.exports.prefix', 'private/governance/exports'), '/');
        $expiresAt = now()->addMinutes((int) config('data_controls.exports.expires_minutes', 240));
        $path = sprintf('%s/business-%d/request-%d-%s.json', $prefix, $businessId, $request->id, now()->format('YmdHis'));

        $payload = [
            'generated_at' => now()->toIso8601String(),
            'request_id' => $request->id,
            'business_id' => $businessId,
            'derived_only_note' => 'Export contains business-scoped records; platform runtime and billing truth remain distributed across core services.',
            'records' => [
                'patients' => DB::table('patients')->where('business_id', $businessId)->limit(5000)->get(),
                'appointments' => DB::table('appointments')->where('business_id', $businessId)->limit(5000)->get(),
                'conversation_logs' => DB::table('conversation_logs')->where('business_id', $businessId)->limit(5000)->get(),
                'inbound_webhooks' => DB::table('inbound_webhooks')->where('business_id', $businessId)->limit(5000)->get(),
                'outbound_message_attempts' => DB::table('outbound_message_attempts')->where('business_id', $businessId)->limit(5000)->get(),
            ],
        ];

        Storage::disk($disk)->put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return [
            'artifact_disk' => $disk,
            'artifact_path' => $path,
            'artifact_expires_at' => $expiresAt,
            'summary' => [
                'mode' => 'export',
                'record_counts' => [
                    'patients' => DB::table('patients')->where('business_id', $businessId)->count(),
                    'appointments' => DB::table('appointments')->where('business_id', $businessId)->count(),
                    'conversation_logs' => DB::table('conversation_logs')->where('business_id', $businessId)->count(),
                    'inbound_webhooks' => DB::table('inbound_webhooks')->where('business_id', $businessId)->count(),
                    'outbound_message_attempts' => DB::table('outbound_message_attempts')->where('business_id', $businessId)->count(),
                ],
            ],
        ];
    }

    /**
     * @return array{summary: array<string, mixed>, legal_hold_applied: bool}
     */
    private function executeDeletion(DataGovernanceRequest $request): array
    {
        $businessId = $request->business_id;
        abort_if($businessId === null, 422, 'Business-scoped deletion request is required.');

        $filters = $request->requested_filters ?? [];
        $requestedMode = (string) ($filters['mode'] ?? 'anonymize');
        $policy = $request->execution_policy ?? $this->resolveDeletionPolicy($businessId);

        $legalHoldApplied = (bool) ($policy['legal_hold_active'] ?? false);
        $effectiveMode = $requestedMode;

        if ($legalHoldApplied || (($policy['allow_hard_delete'] ?? false) !== true)) {
            $effectiveMode = 'anonymize';
        }

        $anonymizedPatients = DB::table('patients')
            ->where('business_id', $businessId)
            ->update([
                'name' => 'Redacted',
                'phone' => null,
                'email' => null,
                'notes' => null,
                'updated_at' => now(),
            ]);

        $anonymizedInbound = DB::table('inbound_webhooks')
            ->where('business_id', $businessId)
            ->update([
                'sender_name' => null,
                'message_text' => null,
                'normalized_payload' => null,
                'updated_at' => now(),
            ]);

        $anonymizedOutbound = DB::table('outbound_message_attempts')
            ->where('business_id', $businessId)
            ->update([
                'message_text' => '[redacted]',
                'response_body' => null,
                'last_error' => null,
                'updated_at' => now(),
            ]);

        $anonymizedConversations = DB::table('conversation_logs')
            ->where('business_id', $businessId)
            ->update([
                'messages' => json_encode([]),
                'updated_at' => now(),
            ]);

        $hardDeleted = [
            'inbound_webhooks' => 0,
            'outbound_message_attempts' => 0,
            'conversation_logs' => 0,
        ];

        if ($effectiveMode === 'hard_delete') {
            // Conservative hard-delete: transient messaging artifacts only.
            // Billing, audit, and legal-retention records are preserved.
            $hardDeleted['outbound_message_attempts'] = DB::table('outbound_message_attempts')
                ->where('business_id', $businessId)
                ->delete();
            $hardDeleted['inbound_webhooks'] = DB::table('inbound_webhooks')
                ->where('business_id', $businessId)
                ->delete();
            $hardDeleted['conversation_logs'] = DB::table('conversation_logs')
                ->where('business_id', $businessId)
                ->delete();
        }

        return [
            'legal_hold_applied' => $legalHoldApplied,
            'summary' => [
                'mode' => 'delete',
                'requested_mode' => $requestedMode,
                'effective_mode' => $effectiveMode,
                'preserved_domains' => ['billing', 'audit', 'legal_retention'],
                'anonymized' => [
                    'patients' => $anonymizedPatients,
                    'inbound_webhooks' => $anonymizedInbound,
                    'outbound_message_attempts' => $anonymizedOutbound,
                    'conversation_logs' => $anonymizedConversations,
                ],
                'hard_deleted' => $hardDeleted,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDeletionPolicy(?int $businessId): array
    {
        $setting = $businessId !== null
            ? BusinessDataRetentionSetting::query()->where('business_id', $businessId)->first()
            : null;

        return [
            'legal_hold_active' => $setting?->legal_hold_until !== null && $setting->legal_hold_until->isFuture(),
            'allow_hard_delete' => $setting?->deletion_strategy === 'hard_delete',
        ];
    }
}
