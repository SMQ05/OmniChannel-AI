<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Patient — a person who has interacted with the business via
 * WhatsApp or Messenger.
 *
 * Patients are identified by their messaging platform identity
 * (platform_user_id + platform) and are scoped to a single business.
 * The unique constraint ensures one Patient record per person per
 * channel per tenant.
 *
 * @property int              $id
 * @property int              $business_id
 * @property string           $name
 * @property string|null      $phone
 * @property string|null      $email
 * @property string           $platform_user_id   WhatsApp user ID or Messenger PSID
 * @property string           $platform           whatsapp|messenger
 * @property string|null      $notes
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 * @property-read Business    $business
 */
class Patient extends Model
{
    /** @var string */
    protected $table = 'patients';

    /** @var list<string> */
    protected $fillable = [
        'business_id',
        'name',
        'phone',
        'email',
        'platform_user_id',
        'platform',
        'notes',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
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
     * The business (tenant) this patient belongs to.
     *
     * @return BelongsTo<Business, Patient>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * All appointments booked by this patient.
     *
     * @return HasMany<Appointment>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * All conversation logs for this patient.
     *
     * @return HasMany<ConversationLog>
     */
    public function conversationLogs(): HasMany
    {
        return $this->hasMany(ConversationLog::class);
    }
}
