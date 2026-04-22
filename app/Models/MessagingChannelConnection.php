<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagingChannelConnection extends Model
{
    protected $table = 'messaging_channel_connections';

    protected $fillable = [
        'business_id',
        'channel',
        'provider',
        'status',
        'credentials',
        'runtime_config',
        'connected_at',
        'last_tested_at',
        'last_test_status',
        'last_test_message',
        'disconnected_at',
        'disconnected_by_user_id',
        'disconnect_reason',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'runtime_config' => 'encrypted:array',
            'connected_at' => 'datetime',
            'last_tested_at' => 'datetime',
            'disconnected_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function disconnectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disconnected_by_user_id');
    }
}
