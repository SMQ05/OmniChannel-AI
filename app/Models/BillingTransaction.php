<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingTransaction extends Model
{
    protected $table = 'billing_transactions';

    protected $fillable = [
        'business_id',
        'billing_account_id',
        'billing_document_id',
        'business_subscription_id',
        'type',
        'status',
        'direction',
        'currency',
        'amount_minor',
        'provider_driver',
        'provider_transaction_ref',
        'provider_event_ref',
        'idempotency_key',
        'effective_at',
        'settled_at',
        'provider_metadata',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'settled_at' => 'datetime',
            'provider_metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function billingAccount(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class, 'billing_document_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BusinessSubscription::class, 'business_subscription_id');
    }

    public function balanceEntries(): HasMany
    {
        return $this->hasMany(BillingBalanceEntry::class);
    }
}
