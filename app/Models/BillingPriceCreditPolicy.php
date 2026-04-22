<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingPriceCreditPolicy extends Model
{
    protected $table = 'billing_price_credit_policies';

    protected $fillable = [
        'billing_price_id',
        'code',
        'balance_bucket',
        'currency',
        'amount_minor',
        'grant_cadence',
        'expires_with_period',
        'carry_forward',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'expires_with_period' => 'boolean',
            'carry_forward' => 'boolean',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function billingPrice(): BelongsTo
    {
        return $this->belongsTo(BillingPrice::class);
    }
}
