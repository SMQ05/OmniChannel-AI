<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingDocumentLine extends Model
{
    protected $table = 'billing_document_lines';

    protected $fillable = [
        'billing_document_id',
        'billing_price_id',
        'line_type',
        'metric',
        'description',
        'quantity',
        'unit_amount_minor',
        'subtotal_minor',
        'period_start',
        'period_end',
        'source_key',
        'reference_type',
        'reference_id',
        'snapshot',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(BillingDocument::class, 'billing_document_id');
    }

    public function billingPrice(): BelongsTo
    {
        return $this->belongsTo(BillingPrice::class);
    }
}
