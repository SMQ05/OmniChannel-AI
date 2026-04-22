<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * BusinessLaunchState - admin workflow state for launch readiness tracking.
 *
 * This model tracks ADMIN WORKFLOW state only. It is NOT runtime truth.
 * Launch readiness is derived from:
 *  - ChannelReadinessService (messaging)
 *  - BillingLedgerService (billing)
 *  - VoiceConfigurationService (voice)
 *  - DiagnosticsService (diagnostics)
 *  - Business operations (onboarding)
 *
 * Do not use this model to determine if a business can operate - use
 * the derived readiness from the above services instead.
 *
 * @property int                        $id
 * @property int                        $business_id
 * @property string                     $launch_stage
 * @property \Carbon\Carbon             $onboarding_started_at
 * @property \Carbon\Carbon             $onboarding_completed_at
 * @property \Carbon\Carbon             $launch_approved_at
 * @property \Carbon\Carbon             $live_at
 * @property bool                       $is_messaging_ready
 * @property bool                       $is_billing_ready
 * @property bool                       $is_voice_ready
 * @property bool                       $is_diagnostics_ready
 * @property bool                       $is_operations_ready
 * @property bool                       $can_skip_readiness
 * @property \Carbon\Carbon             $readiness_snapshot_at
 * @property \Carbon\Carbon             $created_at
 * @property \Carbon\Carbon             $updated_at
 */
class BusinessLaunchState extends Model
{
    protected $table = 'business_launch_states';

    protected $fillable = [
        'business_id',
        'launch_stage',
        'onboarding_started_at',
        'onboarding_completed_at',
        'launch_approved_at',
        'live_at',
        'is_messaging_ready',
        'is_billing_ready',
        'is_voice_ready',
        'is_diagnostics_ready',
        'is_operations_ready',
        'can_skip_readiness',
        'readiness_snapshot_at',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_started_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'launch_approved_at' => 'datetime',
            'live_at' => 'datetime',
            'is_messaging_ready' => 'boolean',
            'is_billing_ready' => 'boolean',
            'is_voice_ready' => 'boolean',
            'is_diagnostics_ready' => 'boolean',
            'is_operations_ready' => 'boolean',
            'can_skip_readiness' => 'boolean',
            'readiness_snapshot_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the business that this launch state belongs to.
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the subscription record for this business.
     */
    public function subscription(): HasOne
    {
        return $this->belongsTo(BusinessSubscription::class, 'business_id', 'business_id');
    }

    /**
     * Get the incidents for this business.
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(IncidentBanner::class, 'business_id');
    }

    /**
     * Get the user who last approved launch for this business.
     */
    public function lastApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }
}
