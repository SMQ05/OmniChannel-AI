<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPriceMetricRate extends Model
{
    protected $table = 'billing_price_metric_rates';

    protected $fillable = [
        'billing_price_id',
        'metric',
        'currency',
        'billable_unit',
        'unit_size',
        'aggregation_strategy',
        'rounding_mode',
        'pricing_model',
        'unit_amount_minor',
        'free_units',
        'cap_units',
        'balance_bucket',
        'event_filters',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'unit_size' => 'decimal:4',
            'free_units' => 'decimal:4',
            'cap_units' => 'decimal:4',
            'event_filters' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function billingPrice(): BelongsTo
    {
        return $this->belongsTo(BillingPrice::class);
    }
}
