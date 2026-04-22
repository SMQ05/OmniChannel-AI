<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataGovernanceRequest extends Model
{
    public const TYPE_EXPORT = 'export';
    public const TYPE_DELETE = 'delete';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected $table = 'data_governance_requests';

    protected $fillable = [
        'business_id',
        'request_scope',
        'request_type',
        'status',
        'requested_by_user_id',
        'approved_by_user_id',
        'executed_by_user_id',
        'request_reason',
        'approval_reason',
        'rejection_reason',
        'requested_filters',
        'execution_policy',
        'result_summary',
        'artifact_disk',
        'artifact_path',
        'artifact_expires_at',
        'run_token',
        'legal_hold_applied',
        'requested_at',
        'approved_at',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_filters' => 'array',
            'execution_policy' => 'array',
            'result_summary' => 'array',
            'legal_hold_applied' => 'boolean',
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'artifact_expires_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    public function isExport(): bool
    {
        return $this->request_type === self::TYPE_EXPORT;
    }

    public function isDeletion(): bool
    {
        return $this->request_type === self::TYPE_DELETE;
    }

    public function artifactIsExpired(): bool
    {
        return $this->artifact_expires_at !== null && $this->artifact_expires_at->isPast();
    }
}
