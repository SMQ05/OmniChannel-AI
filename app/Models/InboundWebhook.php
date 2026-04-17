<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $business_id
 * @property string $channel
 * @property string $business_slug
 * @property string $correlation_id
 * @property string $idempotency_key
 * @property string|null $external_message_id
 * @property string|null $sender_platform_id
 * @property string|null $sender_name
 * @property string|null $message_text
 * @property string|null $message_type
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $normalized_payload
 * @property bool $signature_valid
 * @property string $status
 * @property string|null $queue_connection
 * @property string|null $queue_name
 * @property int $attempt_count
 * @property string|null $last_error
 * @property \Illuminate\Support\Carbon|null $received_at
 * @property \Illuminate\Support\Carbon|null $dispatched_at
 * @property \Illuminate\Support\Carbon|null $processed_at
 * @property \Illuminate\Support\Carbon|null $last_replayed_at
 */
class InboundWebhook extends Model
{
    protected $table = 'inbound_webhooks';

    protected $fillable = [
        'business_id',
        'channel',
        'business_slug',
        'correlation_id',
        'idempotency_key',
        'external_message_id',
        'sender_platform_id',
        'sender_name',
        'message_text',
        'message_type',
        'payload',
        'normalized_payload',
        'signature_valid',
        'status',
        'queue_connection',
        'queue_name',
        'attempt_count',
        'last_error',
        'received_at',
        'dispatched_at',
        'processed_at',
        'last_replayed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'normalized_payload' => 'array',
            'signature_valid' => 'boolean',
            'received_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'processed_at' => 'datetime',
            'last_replayed_at' => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function outboundAttempts(): HasMany
    {
        return $this->hasMany(OutboundMessageAttempt::class);
    }
}
