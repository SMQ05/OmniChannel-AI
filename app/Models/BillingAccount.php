<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingAccount extends Model
{
    protected $table = 'billing_accounts';

    protected $fillable = [
        'business_id',
        'provider_driver',
        'provider_account_ref',
        'currency',
        'billing_email',
        'invoice_email',
        'collection_status',
        'default_payment_state',
        'portal_capable',
        'provider_metadata',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'portal_capable' => 'boolean',
            'provider_metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BillingDocument::class);
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
