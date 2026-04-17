<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $conversation_log_id
 * @property int|null $inbound_webhook_id
 * @property string $channel
 * @property string $recipient_platform_id
 * @property string $idempotency_key
 * @property string $correlation_id
 * @property string $status
 * @property string $message_text
 * @property string|null $provider_message_id
 * @property int|null $http_status
 * @property string|null $failure_class
 * @property string|null $response_body
 * @property string|null $last_error
 * @property int $attempts
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $last_attempted_at
 * @property \Illuminate\Support\Carbon|null $sent_at
 */
class OutboundMessageAttempt extends Model
{
    protected $table = 'outbound_message_attempts';

    protected $fillable = [
        'business_id',
        'conversation_log_id',
        'inbound_webhook_id',
        'channel',
        'recipient_platform_id',
        'idempotency_key',
        'correlation_id',
        'status',
        'message_text',
        'provider_message_id',
        'http_status',
        'failure_class',
        'response_body',
        'last_error',
        'attempts',
        'meta',
        'last_attempted_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'last_attempted_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function conversationLog(): BelongsTo
    {
        return $this->belongsTo(ConversationLog::class);
    }

    public function inboundWebhook(): BelongsTo
    {
        return $this->belongsTo(InboundWebhook::class);
    }
}
