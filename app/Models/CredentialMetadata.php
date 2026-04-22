<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CredentialMetadata - metadata workspace for credential rotation tracking.
 *
 * This is a MASKED METADATA workspace only. It does NOT store actual secrets.
 * Real credentials remain in their original sources:
 *  - MessagingChannelConnection.credentials
 *  - VoiceChannel configuration
 *  - BillingAccount provider_metadata
 *
 * This model only tracks:
 *  - Provider identification
 *  - Rotation schedule
 *  - Last verification status
 *  - Admin notes
 *
 * @property int                        $id
 * @property string                     $source_type
 * @property int                        $source_id
 * @property string                     $provider
 * @property string                     $key_name
 * @property string|null                $description
 * @property bool                       $is_active
 * @property \Carbon\Carbon             $created_at
 * @property \Carbon\Carbon             $last_rotated_at
 * @property \Carbon\Carbon             $next_rotation_due_at
 * @property int                        $rotation_interval_days
 * @property \Carbon\Carbon             $last_verified_at
 * @property string|null                $last_verification_status
 * @property string|null                $last_verification_message
 * @property int|null                   $last_verified_by_user_id
 * @property array|null                 $metadata
 * @property \Carbon\Carbon             $created_at
 * @property \Carbon\Carbon             $updated_at
 */
class CredentialMetadata extends Model
{
    protected $table = 'credential_metadata';

    protected $fillable = [
        'source_type',
        'source_id',
        'provider',
        'key_name',
        'description',
        'is_active',
        'created_at',
        'last_rotated_at',
        'next_rotation_due_at',
        'rotation_interval_days',
        'last_verified_at',
        'last_verification_status',
        'last_verification_message',
        'last_verified_by_user_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'last_rotated_at' => 'datetime',
            'next_rotation_due_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the source record that this metadata belongs to.
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo($this->getSourceModelClass($this->source_type), 'source_id');
    }

    /**
     * Get the user who last verified this credential.
     */
    public function lastVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_verified_by_user_id');
    }

    /**
     * Get the model class for a source type.
     */
    private function getSourceModelClass(string $sourceType): string
    {
        return match ($sourceType) {
            'messaging_connection' => MessagingChannelConnection::class,
            'voice_channel' => VoiceChannel::class,
            'billing_account' => BillingAccount::class,
            default => throw new \InvalidArgumentException("Unknown source type: {$sourceType}"),
        };
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    /**
     * Scope to get credentials for a specific provider.
     */
    public function scopeForProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Scope to get credentials needing rotation.
     */
    public function scopeNeedingRotation($query)
    {
        return $query->where('is_active', true)
            ->whereNotNull('next_rotation_due_at')
            ->where('next_rotation_due_at', '<=', now());
    }

    /**
     * Scope to get credentials with failed verification.
     */
    public function scopeWithFailedVerification($query)
    {
        return $query->where('last_verification_status', 'fail');
    }

    /**
     * Scope to get active credentials only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Is this credential currently active?
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Does this credential need rotation?
     */
    public function needsRotation(): bool
    {
        return $this->is_active &&
            $this->next_rotation_due_at !== null &&
            $this->next_rotation_due_at <= now();
    }

    /**
     * Get the provider label for display.
     */
    public function providerLabel(): string
    {
        return match ($this->provider) {
            'meta_cloud' => 'Meta WhatsApp Cloud',
            'twilio' => 'Twilio',
            'deepgram' => 'Deepgram',
            'openrouter' => 'OpenRouter',
            'elevenlabs' => 'ElevenLabs',
            'openai' => 'OpenAI',
            'anthropic' => 'Anthropic',
            'google' => 'Google',
            default => ucfirst($this->provider),
        };
    }

    /**
     * Get verification status badge class.
     */
    public function verificationStatusClass(): string
    {
        return match ($this->last_verification_status) {
            'pass' => 'bg-green-500 text-white',
            'fail' => 'bg-red-500 text-white',
            'unknown' => 'bg-gray-400 text-white',
            default => 'bg-gray-500 text-white',
        };
    }

    /**
     * Is rotation due or overdue?
     */
    public function isRotationOverdue(): bool
    {
        return $this->next_rotation_due_at !== null &&
            $this->next_rotation_due_at < now();
    }
}
