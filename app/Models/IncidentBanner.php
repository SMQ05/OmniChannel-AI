<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * IncidentBanner - platform or business-specific incident notification.
 *
 * Banners can be:
 *  - Platform-wide (is_platform_wide = true) - shown to all users
 *  - Business-specific (is_platform_wide = false) - shown to specific business
 *
 * Severity levels:
 *  - info    - For general announcements, not urgent
 *  - warning - For degraded functionality, partial outages
 *  - critical - For major incidents requiring immediate attention
 *
 * Status states:
 *  - draft     - Being composed but not yet published
 *  - published - Currently visible to users
 *  - archived  - No longer visible, kept for history
 *  - resolved  - Archived with resolution note
 *
 * @property int                        $id
 * @property string                     $title
 * @property string                     $message
 * @property string                     $severity
 * @property bool                       $is_platform_wide
 * @property int|null                   $business_id
 * @property \Carbon\Carbon             $starts_at
 * @property \Carbon\Carbon             $ends_at
 * @property string                     $status
 * @property int|null                   $published_by_user_id
 * @property \Carbon\Carbon             $published_at
 * @property int|null                   $archived_by_user_id
 * @property \Carbon\Carbon             $archived_at
 * @property int|null                   $resolved_by_user_id
 * @property \Carbon\Carbon             $resolved_at
 * @property \Carbon\Carbon             $created_at
 * @property \Carbon\Carbon             $updated_at
 */
class IncidentBanner extends Model
{
    protected $table = 'incident_banners';

    protected $fillable = [
        'title',
        'message',
        'severity',
        'is_platform_wide',
        'business_id',
        'starts_at',
        'ends_at',
        'status',
        'published_by_user_id',
        'published_at',
        'archived_by_user_id',
        'archived_at',
        'resolved_by_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'resolved_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the business that this banner applies to (nullable for platform banners).
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Get the user who published this banner.
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    /**
     * Get the user who archived this banner.
     */
    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    /**
     * Get the user who resolved this banner.
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope to get currently published banners.
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where(function ($q) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            });
    }

    /**
     * Scope to get platform-wide banners only.
     */
    public function scopePlatformOnly($query)
    {
        return $query->where('is_platform_wide', true);
    }

    /**
     * Scope to get business-specific banners only.
     */
    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    /**
     * Scope to get banners for a specific severity level.
     */
    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Is this banner currently visible?
     */
    public function isVisible(): bool
    {
        return $this->status === 'published' &&
            (!$this->starts_at || $this->starts_at <= now()) &&
            (!$this->ends_at || $this->ends_at > now());
    }

    /**
     * Get severity badge class for UI.
     */
    public function severityClass(): string
    {
        return match ($this->severity) {
            'critical' => 'bg-red-500 text-white',
            'warning' => 'bg-amber-500 text-white',
            default => 'bg-blue-500 text-white',
        };
    }

    /**
     * Get status badge class for UI.
     */
    public function statusClass(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-500 text-white',
            'published' => 'bg-green-500 text-white',
            'archived' => 'bg-gray-400 text-white',
            'resolved' => 'bg-emerald-500 text-white',
            default => 'bg-gray-500 text-white',
        };
    }
}
