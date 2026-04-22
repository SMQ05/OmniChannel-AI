<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Provider — a bookable resource within a business.
 *
 * Represents a doctor, stylist, lawyer, therapist, or any other
 * professional whose time can be booked. Working hours are stored
 * as a weekly JSON schedule; blocked dates are stored in the
 * related ProviderBlockedDate model.
 *
 * @property int                        $id
 * @property int                        $business_id
 * @property string                     $name
 * @property string|null                $title
 * @property string|null                $specialization
 * @property array<string, mixed>|null  $working_hours
 * @property int                        $slot_duration_minutes
 * @property bool                       $is_active
 * @property \Carbon\Carbon             $created_at
 * @property \Carbon\Carbon             $updated_at
 * @property-read Business              $business
 */
class Provider extends Model
{
    /** @var string */
    protected $table = 'providers';

    /** @var list<string> */
    protected $fillable = [
        'business_id',
        'name',
        'title',
        'specialization',
        'working_hours',
        'slot_duration_minutes',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'working_hours'         => 'array',
            'slot_duration_minutes' => 'integer',
            'is_active'             => 'boolean',
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
     * The business this provider belongs to.
     *
     * @return BelongsTo<Business, Provider>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Dates on which this provider is explicitly unavailable.
     *
     * @return HasMany<ProviderBlockedDate>
     */
    public function blockedDates(): HasMany
    {
        return $this->hasMany(ProviderBlockedDate::class);
    }

    /**
     * All appointments assigned to this provider.
     *
     * @return HasMany<Appointment>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * Structured services this provider can deliver.
     *
     * @return BelongsToMany<BusinessService>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(BusinessService::class, 'business_service_provider')
            ->withTimestamps()
            ->orderBy('business_services.sort_order')
            ->orderBy('business_services.name');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the working hours config for a given day of the week.
     *
     * @param  string  $day  Lowercase day name, e.g. 'monday'
     * @return array<string, mixed>
     */
    public function scheduleForDay(string $day): array
    {
        return $this->working_hours[strtolower($day)] ?? ['active' => false];
    }

    /**
     * Determine whether the provider works on a given day of the week.
     *
     * @param  string  $day  Lowercase day name, e.g. 'monday'
     */
    public function isActiveOnDay(string $day): bool
    {
        return (bool) ($this->scheduleForDay($day)['active'] ?? false);
    }

    /**
     * Return a display label combining title and name when a title exists.
     */
    public function displayName(): string
    {
        return $this->title
            ? "{$this->title} {$this->name}"
            : $this->name;
    }
}
