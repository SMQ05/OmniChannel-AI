<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VoiceSession extends Model
{
    protected $table = 'voice_sessions';

    protected $fillable = [
        'uuid',
        'business_id',
        'voice_channel_id',
        'patient_id',
        'legacy_call_log_id',
        'provider',
        'provider_call_id',
        'transport_stream_id',
        'openai_session_id',
        'deepgram_session_id',
        'direction',
        'status',
        'from_number',
        'to_number',
        'initiated_at',
        'connected_at',
        'ended_at',
        'last_activity_at',
        'transfer_requested_at',
        'callback_requested_at',
        'fallback_mode',
        'handoff_reason',
        'metrics',
        'context',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'context' => 'array',
            'meta' => 'array',
            'initiated_at' => 'datetime',
            'connected_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'transfer_requested_at' => 'datetime',
            'callback_requested_at' => 'datetime',
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

    public function legacyCallLog(): BelongsTo
    {
        return $this->belongsTo(CallLog::class, 'legacy_call_log_id');
    }

    public function turns(): HasMany
    {
        return $this->hasMany(VoiceTurn::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(VoiceEvent::class);
    }

    public function summary(): HasOne
    {
        return $this->hasOne(VoiceSummary::class);
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(VoiceUsageEvent::class);
    }
}
