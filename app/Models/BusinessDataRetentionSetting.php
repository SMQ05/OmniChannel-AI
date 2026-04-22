<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDataRetentionSetting extends Model
{
    protected $table = 'business_data_retention_settings';

    protected $fillable = [
        'business_id',
        'conversation_logs_days',
        'inbound_webhooks_days',
        'outbound_attempts_days',
        'voice_events_days',
        'deletion_strategy',
        'legal_hold_until',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'conversation_logs_days' => 'integer',
            'inbound_webhooks_days' => 'integer',
            'outbound_attempts_days' => 'integer',
            'voice_events_days' => 'integer',
            'legal_hold_until' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }
}
