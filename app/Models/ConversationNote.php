<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationNote extends Model
{
    protected $table = 'conversation_notes';

    protected $fillable = [
        'conversation_log_id',
        'user_id',
        'note',
    ];

    public function conversationLog(): BelongsTo
    {
        return $this->belongsTo(ConversationLog::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
