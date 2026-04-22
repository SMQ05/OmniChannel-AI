<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringSnapshot extends Model
{
    protected $table = 'monitoring_snapshots';

    protected $fillable = [
        'scope',
        'business_id',
        'snapshot_type',
        'aggregates',
        'source_context',
        'captured_at',
    ];

    protected function casts(): array
    {
        return [
            'aggregates' => 'array',
            'source_context' => 'array',
            'captured_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
