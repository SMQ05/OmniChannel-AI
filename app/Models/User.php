<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property string                $role       super_admin|business_owner|staff
 * @property \Carbon\Carbon        $created_at
 * @property \Carbon\Carbon        $updated_at
 * @property-read Business|null    $business
 */
class User extends Authenticatable implements MustVerifyEmailContract
{
    use HasFactory;
    use MustVerifyEmail;
    use Notifiable;

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
}
