<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Structured service offering for a single business.
 *
 * Keeps the operational catalog additive alongside the legacy
 * ai_config.services array until the structured path is fully proven.
 *
 * @property int $id
 * @property int $business_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $duration_minutes
 * @property string|null $price
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, mixed>|null $booking_rules
 */
class BusinessService extends Model
{
    /** @var string */
    protected $table = 'business_services';

    /** @var list<string> */
    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'description',
        'duration_minutes',
        'price',
        'is_active',
        'sort_order',
        'booking_rules',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'booking_rules' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::saving(function (self $service): void {
            if ($service->slug === '' || $service->isDirty('name')) {
                $service->slug = Str::slug($service->name);
            }
        });
    }

    /**
     * @return BelongsTo<Business, BusinessService>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return BelongsToMany<Provider>
     */
    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class, 'business_service_provider')
            ->withTimestamps()
            ->orderBy('providers.name');
    }

    /**
     * @return HasMany<Appointment>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return is_array($this->booking_rules) ? $this->booking_rules : [];
    }

    public function bookingRuleInt(string $key, int $default = 0): int
    {
        return (int) ($this->rules()[$key] ?? $default);
    }

    /**
     * @return array{name: string, duration_min: int, price?: float}
     */
    public function toLegacyPromptShape(): array
    {
        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'duration_min' => $this->duration_minutes,
        ];

        if ($this->price !== null) {
            $payload['price'] = (float) $this->price;
        }

        return $payload;
    }
}
