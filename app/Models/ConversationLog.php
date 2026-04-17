<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ConversationLog — persisted record of a single patient session.
 *
 * The messages JSON array is the authoritative conversation history
 * that is passed to the AI agent on every turn. The Redis session
 * mirrors this for fast access and is refreshed on each message;
 * this DB record is the durable source of truth.
 *
 * human_mode, when true, signals that an operator has taken over
 * the conversation and the AI should remain silent for this session.
 *
 * @property int                        $id
 * @property int                        $business_id
 * @property int                        $patient_id
 * @property int|null                   $appointment_id
 * @property string                     $channel            whatsapp|messenger
 * @property array<int, mixed>|null     $messages
 * @property string|null                $ai_model_used
 * @property bool                       $human_mode
 * @property \Carbon\Carbon             $session_started_at
 * @property \Carbon\Carbon|null        $session_ended_at
 * @property \Carbon\Carbon             $created_at
 * @property \Carbon\Carbon             $updated_at
 * @property-read Business              $business
 * @property-read Patient               $patient
 * @property-read Appointment|null      $appointment
 */
class ConversationLog extends Model
{
    /**
     * Maximum number of messages retained in the JSON payload.
     *
     * When this limit is reached, the oldest message is rolled off
     * so the array never exceeds this size. This keeps the PostgreSQL
     * JSONB column bounded and the AI context payload predictable.
     */
    public const MAX_MESSAGES = 50;

    /** @var string */
    protected $table = 'conversation_logs';

    /** @var list<string> */
    protected $fillable = [
        'business_id',
        'patient_id',
        'appointment_id',
        'channel',
        'messages',
        'ai_model_used',
        'human_mode',
        'session_started_at',
        'session_ended_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'messages'           => 'array',
            'human_mode'         => 'boolean',
            'session_started_at' => 'datetime',
            'session_ended_at'   => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Global scope
    // -------------------------------------------------------------------------

    /**
     * Boot model — register the global tenant scope.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The business (tenant) this conversation belongs to.
     *
     * @return BelongsTo<Business, ConversationLog>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The patient who participated in this conversation.
     *
     * @return BelongsTo<Patient, ConversationLog>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The appointment that resulted from this conversation, if any.
     *
     * @return BelongsTo<Appointment, ConversationLog>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function outboundAttempts(): HasMany
    {
        return $this->hasMany(OutboundMessageAttempt::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ConversationNote::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Append a single message turn to the messages array.
     *
     * Enforces a MAX_MESSAGES rolling window: once the array reaches
     * the limit, the oldest entry is dropped before the new one is
     * appended. This keeps the PostgreSQL JSONB payload bounded and
     * prevents AI context bloat.
     *
     * Timestamps are always written in UTC (ISO-8601) regardless of
     * the application or business timezone.
     *
     * Does NOT persist — call save() after this method.
     *
     * @param  string  $role     'user' or 'assistant'
     * @param  string  $content  The message text
     */
    public function appendMessage(string $role, string $content): void
    {
        $history = $this->messages ?? [];

        $history[] = [
            'role'      => $role,
            'content'   => $content,
            'timestamp' => now()->utc()->toISOString(),
        ];

        // Roll off oldest messages once the cap is exceeded
        if (count($history) > self::MAX_MESSAGES) {
            $history = array_slice($history, -self::MAX_MESSAGES);
        }

        $this->messages = array_values($history);
    }

    /**
     * Return the messages array formatted for the AI SDK (role + content only).
     *
     * @return list<array{role: string, content: string}>
     */
    public function toAiMessages(): array
    {
        return array_map(
            static fn (array $msg): array => [
                'role'    => $msg['role'],
                'content' => $msg['content'],
            ],
            $this->messages ?? [],
        );
    }

    /**
     * Mark this session as handed off to a human operator.
     *
     * Does NOT persist — call save() after this method.
     */
    public function flagHumanHandoff(): void
    {
        $this->human_mode = true;
    }

    /**
     * Close this session by recording the end timestamp.
     *
     * Does NOT persist — call save() after this method.
     */
    public function closeSession(): void
    {
        $this->session_ended_at = now();
    }
}
