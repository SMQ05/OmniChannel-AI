<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceEvent extends Model
{
    protected $table = 'voice_events';

    protected $fillable = [
        'business_id',
        'voice_session_id',
        'event_type',
        'source',
        'idempotency_key',
        'correlation_id',
        'severity',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
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
