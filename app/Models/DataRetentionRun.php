<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DataRetentionRun extends Model
{
    protected $table = 'data_retention_runs';

    protected $fillable = [
        'run_key',
        'mode',
        'scope',
        'business_id',
        'triggered_by_user_id',
        'status',
        'summary',
        'command_signature',
        'started_at',
        'finished_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
