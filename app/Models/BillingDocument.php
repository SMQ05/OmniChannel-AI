<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingDocument extends Model
{
    protected $table = 'billing_documents';

    protected $fillable = [
        'business_id',
        'billing_account_id',
        'business_subscription_id',
        'type',
        'status',
        'document_key',
        'number',
        'currency',
        'subtotal_minor',
        'credit_total_minor',
        'tax_total_minor',
        'total_minor',
        'amount_due_minor',
        'amount_paid_minor',
        'period_start',
        'period_end',
        'issued_at',
        'due_at',
        'finalized_at',
        'paid_at',
        'voided_at',
        'provider_driver',
        'provider_document_ref',
        'source',
        'context_snapshot',
        'provider_metadata',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'due_at' => 'datetime',
            'finalized_at' => 'datetime',
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
            'context_snapshot' => 'array',
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

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(BusinessSubscription::class, 'business_subscription_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BillingDocumentLine::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(BillingTransaction::class);
    }

    public function balanceEntries(): HasMany
    {
        return $this->hasMany(BillingBalanceEntry::class);
    }
}
