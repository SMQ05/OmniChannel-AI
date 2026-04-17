<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallLog extends Model
{
    protected $table = 'call_logs';

    protected $fillable = [
        'business_id',
        'voice_channel_id',
        'patient_id',
        'provider_call_id',
        'status',
        'direction',
        'duration_seconds',
        'transcript_excerpt',
        'meta',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function voiceChannel(): BelongsTo
    {
        return $this->belongsTo(VoiceChannel::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
