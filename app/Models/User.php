<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Auth\PermissionMatrix;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User — a human operator account attached to a business tenant.
 *
 * super_admin users have a NULL business_id and can access all tenants.
 * business_owner and staff users are scoped to their business_id.
 *
 * @property int                   $id
 * @property int|null              $business_id
 * @property string                $name
 * @property string                $email
 * @property string                $password
 * @property string                $role       super_admin|support_admin|business_owner|manager|receptionist|staff
 * @property \Carbon\Carbon        $created_at
 * @property \Carbon\Carbon        $updated_at
 * @property-read Business|null    $business
 */
class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory;
    use MustVerifyEmail;
    use Notifiable;

    /**
     * @var array<string, string>
     */
    private const BUSINESS_ROLE_LABELS = [
        'business_owner' => 'Business Owner',
        'manager' => 'Manager',
        'receptionist' => 'Receptionist',
        'staff' => 'Staff',
    ];

    /** @var string */
    protected $table = 'users';

    /** @var list<string> */
    protected $fillable = [
        'business_id',
        'name',
        'email',
        'password',
        'role',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password'          => 'hashed',
            'email_verified_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The business (tenant) this user belongs to.
     * Returns null for super_admin users.
     *
     * @return BelongsTo<Business, User>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function conversationNotes(): HasMany
    {
        return $this->hasMany(ConversationNote::class);
    }

    public function sentTeamInvites(): HasMany
    {
        return $this->hasMany(TeamInvite::class, 'invited_by_user_id');
    }

    public function acceptedTeamInvites(): HasMany
    {
        return $this->hasMany(TeamInvite::class, 'accepted_by_user_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_user_id');
    }

    public function requestedGovernanceRequests(): HasMany
    {
        return $this->hasMany(DataGovernanceRequest::class, 'requested_by_user_id');
    }

    public function approvedGovernanceRequests(): HasMany
    {
        return $this->hasMany(DataGovernanceRequest::class, 'approved_by_user_id');
    }

    public function executedGovernanceRequests(): HasMany
    {
        return $this->hasMany(DataGovernanceRequest::class, 'executed_by_user_id');
    }

    public function retentionRuns(): HasMany
    {
        return $this->hasMany(DataRetentionRun::class, 'triggered_by_user_id');
    }

    // -------------------------------------------------------------------------
    // Role helpers
    // -------------------------------------------------------------------------

    /**
     * Determine whether this user is a platform super admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    /**
     * Determine whether this user owns the business they belong to.
     */
    public function isBusinessOwner(): bool
    {
        return $this->role === 'business_owner';
    }

    /**
     * Determine whether this user is a staff member.
     */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isReceptionist(): bool
    {
        return $this->role === 'receptionist';
    }

    public function canPermission(string $permissionKey): bool
    {
        return app(PermissionMatrix::class)->allows($this, $permissionKey);
    }

    public function roleLabel(): string
    {
        return self::BUSINESS_ROLE_LABELS[$this->role] ?? str_replace('_', ' ', ucfirst($this->role));
    }

    /**
     * @return array<string, string>
     */
    public static function assignableBusinessRoles(): array
    {
        return collect(self::BUSINESS_ROLE_LABELS)
            ->except('business_owner')
            ->all();
    }
}
