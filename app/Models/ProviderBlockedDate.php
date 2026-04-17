<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProviderBlockedDate — an individual date on which a provider
 * is unavailable (holiday, leave, etc.).
 *
 * The slot-availability calculator excludes these dates before
 * exposing available slots to the AI agent.
 *
 * @property int              $id
 * @property int              $provider_id
 * @property \Carbon\Carbon   $blocked_date
 * @property string|null      $reason
 * @property \Carbon\Carbon   $created_at
 * @property \Carbon\Carbon   $updated_at
 * @property-read Provider    $provider
 */
class ProviderBlockedDate extends Model
{
    /** @var string */
    protected $table = 'provider_blocked_dates';

    /** @var list<string> */
    protected $fillable = [
        'provider_id',
        'blocked_date',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocked_date' => 'date',
        ];
    }

    // -------------------------------------------------------------------------
    // Global scope
    // -------------------------------------------------------------------------

    /**
     * Boot model — register the global tenant scope via provider relationship.
     *
     * Note: this model does not have a direct business_id column.
     * Tenant isolation is enforced at query time by always loading
     * blocked dates through a scoped Provider relationship.
     */
    protected static function booted(): void
    {
        // No direct business_id column — tenant isolation is achieved by
        // always querying through a provider that already has TenantScope.
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The provider this blocked date belongs to.
     *
     * @return BelongsTo<Provider, ProviderBlockedDate>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
