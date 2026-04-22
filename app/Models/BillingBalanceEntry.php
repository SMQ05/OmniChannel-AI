<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingBalanceEntry extends Model
{
    protected $table = 'billing_balance_entries';

    protected $fillable = [
        'business_id',
        'billing_account_id',
        'business_subscription_id',
        'billing_document_id',
        'billing_transaction_id',
        'entry_key',
        'entry_type',
        'balance_bucket',
        'direction',
        'currency',
        'amount_minor',
        'effective_at',
        'expires_at',
        'applies_to_entry_id',
        'source_type',
        'source_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'effective_at' => 'datetime',
            'expires_at' => 'datetime',
            'metadata' => 'array',
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

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BusinessSubscription::class, 'business_subscription_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class, 'billing_document_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(BillingTransaction::class, 'billing_transaction_id');
    }

    public function appliesTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'applies_to_entry_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(self::class, 'applies_to_entry_id');
    }
}
