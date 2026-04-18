<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceTurn extends Model
{
    protected $table = 'voice_turns';

    protected $fillable = [
        'voice_session_id',
        'sequence',
        'role',
        'source',
        'text',
        'transcript',
        'tool_name',
        'tool_status',
        'interrupted',
        'latency_ms',
        'started_at',
        'ended_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'interrupted' => 'boolean',
            'meta' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function voiceSession(): BelongsTo
    {
        return $this->belongsTo(VoiceSession::class);
    }
}
