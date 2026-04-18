<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceUsageEvent extends Model
{
    protected $table = 'voice_usage_events';

    protected $fillable = [
        'business_id',
        'voice_session_id',
        'provider',
        'metric',
        'quantity',
        'unit',
        'cost_estimate',
        'meta',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'cost_estimate' => 'decimal:4',
            'meta' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function voiceSession(): BelongsTo
    {
        return $this->belongsTo(VoiceSession::class);
    }
}
