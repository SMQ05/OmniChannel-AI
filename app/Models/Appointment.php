<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Appointment — a confirmed (or pending/cancelled/etc.) booking.
 *
 * reminder_sent_at is a JSON map keyed by offset_hours (as strings)
 * with ISO-8601 timestamp values when sent, or null when not yet sent.
 * Example: { "24": "2026-04-19T14:00:00Z", "2": null }
 *
 * @property int                        $id
 * @property int                        $business_id
 * @property int                        $provider_id
 * @property int                        $patient_id
 * @property string                     $service_type
 * @property \Carbon\Carbon             $start_time
 * @property \Carbon\Carbon             $end_time
 * @property string                     $status       pending|confirmed|cancelled|completed|no_show
 * @property string                     $booked_via   whatsapp|messenger|manual
 * @property array<string, mixed>|null  $reminder_sent_at
 * @property bool                       $synced_to_calendar
 * @property bool                       $synced_to_sheets
 * @property string|null                $notes
 * @property \Carbon\Carbon             $created_at
 * @property \Carbon\Carbon             $updated_at
 * @property-read Business              $business
 * @property-read Provider              $provider
 * @property-read Patient               $patient
 */
class Appointment extends Model
{
    /** @var string */
    protected $table = 'appointments';

    /** @var list<string> */
    protected $fillable = [
        'business_id',
        'provider_id',
        'patient_id',
        'service_type',
        'start_time',
        'end_time',
        'status',
        'booked_via',
        'reminder_sent_at',
        'synced_to_calendar',
        'synced_to_sheets',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time'         => 'datetime',
            'end_time'           => 'datetime',
            'reminder_sent_at'   => 'array',
            'synced_to_calendar' => 'boolean',
            'synced_to_sheets'   => 'boolean',
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
     * The business (tenant) this appointment belongs to.
     *
     * @return BelongsTo<Business, Appointment>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * The provider assigned to this appointment.
     *
     * @return BelongsTo<Provider, Appointment>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    /**
     * The patient who booked this appointment.
     *
     * @return BelongsTo<Patient, Appointment>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * The conversation log entry linked to this appointment (if any).
     *
     * @return HasOne<ConversationLog>
     */
    public function conversationLog(): HasOne
    {
        return $this->hasOne(ConversationLog::class);
    }

    // -------------------------------------------------------------------------
    // Query scopes
    // -------------------------------------------------------------------------

    /**
     * Scope to confirmed appointments only.
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    /**
     * Scope to upcoming appointments (start_time in the future).
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('start_time', '>', now());
    }

    /**
     * Scope to appointments scheduled today (business timezone unaware —
     * callers should apply timezone conversion as needed).
     *
     * @param  Builder<Appointment>  $query
     * @return Builder<Appointment>
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('start_time', today());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether a specific reminder offset has already been sent.
     *
     * @param  int  $offsetHours  The reminder's configured offset in hours
     */
    public function isReminderSent(int $offsetHours): bool
    {
        return isset($this->reminder_sent_at[(string) $offsetHours]);
    }

    /**
     * Mark a reminder offset as sent by recording the current timestamp.
     *
     * Does NOT persist — call save() after this method.
     *
     * @param  int  $offsetHours  The reminder's configured offset in hours
     */
    public function markReminderSent(int $offsetHours): void
    {
        $map = $this->reminder_sent_at ?? [];
        $map[(string) $offsetHours] = now()->toISOString();
        $this->reminder_sent_at = $map;
    }

    /**
     * Determine whether this appointment is still cancellable
     * (i.e. it has not already been cancelled or completed).
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'confirmed'], true);
    }
}
