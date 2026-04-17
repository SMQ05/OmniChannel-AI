<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessSubscription extends Model
{
    protected $table = 'business_subscriptions';

    protected $fillable = [
        'business_id',
        'plan_id',
        'status',
        'included_quotas',
        'overage_counters',
        'feature_flags',
        'warn_at_ratio',
        'enforce_limits',
        'admin_override',
        'current_period_start',
        'current_period_end',
    ];

    protected function casts(): array
    {
        return [
            'included_quotas' => 'array',
            'overage_counters' => 'array',
            'feature_flags' => 'array',
            'warn_at_ratio' => 'decimal:2',
            'enforce_limits' => 'boolean',
            'admin_override' => 'boolean',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
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
}
