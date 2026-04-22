<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessMessagingChannel extends Model
{
    protected $table = 'business_messaging_channels';

    protected $fillable = [
        'business_id',
        'channel',
        'is_enabled',
        'approved_at',
        'enabled_at',
        'disabled_at',
        'disabled_by_user_id',
        'disable_reason',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'approved_at' => 'datetime',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function disabledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by_user_id');
    }
}
