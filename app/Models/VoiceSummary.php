<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceSummary extends Model
{
    protected $table = 'voice_summaries';

    protected $fillable = [
        'voice_session_id',
        'summary',
        'disposition',
        'action_items',
        'booking_outcome',
        'followup_required',
        'structured_data',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'action_items' => 'array',
            'followup_required' => 'boolean',
            'structured_data' => 'array',
            'generated_at' => 'datetime',
        ];
    }

    public function voiceSession(): BelongsTo
    {
        return $this->belongsTo(VoiceSession::class);
    }
}
