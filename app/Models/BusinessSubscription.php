<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BusinessSubscription extends Model
{
    protected $table = 'business_subscriptions';

    protected $fillable = [
        'business_id',
        'plan_id',
        'billing_price_id',
        'status',
        'lifecycle_status',
        'included_quotas',
        'overage_counters',
        'feature_flags',
        'price_snapshot',
        'warn_at_ratio',
        'enforce_limits',
        'admin_override',
        'current_period_start',
        'current_period_end',
        'billing_cycle_anchor_at',
        'next_invoice_at',
        'trial_ends_at',
        'grace_ends_at',
        'cancel_at_period_end',
        'cancel_requested_at',
        'canceled_at',
        'ended_at',
        'past_due_at',
        'suspended_at',
        'reactivated_at',
        'setup_fee_invoiced_at',
        'suspension_reason',
        'provider_contract_ref',
        'provider_metadata',
        // Launch readiness flags
        'has_onboarding_complete',
        'onboarding_completed_at',
        'has_all_channels_configured',
        'all_channels_configured_at',
        'has_billing_configured',
        'billing_configured_at',
        'can_override_readiness_checks',
    ];

    protected function casts(): array
    {
        return [
            'included_quotas' => 'array',
            'overage_counters' => 'array',
            'feature_flags' => 'array',
            'price_snapshot' => 'array',
            'warn_at_ratio' => 'decimal:2',
            'enforce_limits' => 'boolean',
            'admin_override' => 'boolean',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'billing_cycle_anchor_at' => 'datetime',
            'next_invoice_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancel_requested_at' => 'datetime',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
            'past_due_at' => 'datetime',
            'suspended_at' => 'datetime',
            'reactivated_at' => 'datetime',
            'setup_fee_invoiced_at' => 'datetime',
            'provider_metadata' => 'array',
            'has_onboarding_complete' => 'boolean',
            'has_all_channels_configured' => 'boolean',
            'has_billing_configured' => 'boolean',
            'can_override_readiness_checks' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function billingPrice(): BelongsTo
    {
        return $this->belongsTo(BillingPrice::class);
    }

    public function billingDocuments(): HasMany
    {
        return $this->hasMany(BillingDocument::class, 'business_subscription_id');
    }

    public function billingTransactions(): HasMany
    {
        return $this->hasMany(BillingTransaction::class, 'business_subscription_id');
    }

    public function billingBalanceEntries(): HasMany
    {
        return $this->hasMany(BillingBalanceEntry::class, 'business_subscription_id');
    }

    /**
     * Get the launch state for this subscription's business.
     */
    public function launchState(): HasOne
    {
        return $this->hasOne(BusinessLaunchState::class, 'business_id');
    }
}
