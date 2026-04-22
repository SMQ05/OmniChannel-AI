<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingPrice extends Model
{
    protected $table = 'billing_prices';

    protected $fillable = [
        'plan_id',
        'code',
        'version',
        'name',
        'description',
        'status',
        'currency',
        'recurring_amount_minor',
        'recurring_interval_unit',
        'recurring_interval_count',
        'setup_fee_amount_minor',
        'setup_fee_behavior',
        'trial_days',
        'supersedes_billing_price_id',
        'provider_driver',
        'provider_sellable_ref',
        'provider_variant_ref',
        'provider_metadata',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'provider_metadata' => 'array',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_billing_price_id');
    }

    public function metricRates(): HasMany
    {
        return $this->hasMany(BillingPriceMetricRate::class);
    }

    public function creditPolicies(): HasMany
    {
        return $this->hasMany(BillingPriceCreditPolicy::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(BusinessSubscription::class);
    }

    public function documentLines(): HasMany
    {
        return $this->hasMany(BillingDocumentLine::class);
    }
}
