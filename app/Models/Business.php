<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TenantScope;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Business — the tenant root record.
 *
 * Every other tenant-owned model has a business_id FK pointing here.
 * This model itself does NOT use TenantScope because it IS the tenant.
 *
 * @property int                        $id
 * @property string                     $name
 * @property string                     $business_type
 * @property string                     $slug
 * @property string                     $timezone
 * @property string                     $locale
 * @property array<string, mixed>|null  $channel_config
 * @property array<string, mixed>|null  $integration_config
 * @property array<string, mixed>|null  $reminder_settings
 * @property array<string, mixed>|null  $ai_config
 * @property bool                       $is_active
 * @property string                     $plan
 * @property \Carbon\Carbon             $created_at
 * @property \Carbon\Carbon             $updated_at
 */
class Business extends Model
{
    /** @var string */
    protected $table = 'businesses';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'business_type',
        'slug',
        'timezone',
        'locale',
        'channel_config',
        'integration_config',
        'reminder_settings',
        'ai_config',
        'is_active',
        'plan',
    ];

    /**
     * Attribute casting — JSON columns are automatically
     * decoded to PHP arrays on access and re-encoded on save.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel_config'      => 'array',
            'integration_config'  => 'array',
            'reminder_settings'   => 'array',
            'ai_config'           => 'array',
            'is_active'           => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * All user accounts that belong to this business.
     *
     * @return HasMany<User>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * All bookable providers (doctors, stylists, etc.) for this business.
     *
     * @return HasMany<Provider>
     */
    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }

    /**
     * All patients registered under this business.
     *
     * @return HasMany<Patient>
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    /**
     * All appointments belonging to this business.
     *
     * @return HasMany<Appointment>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * All conversation log entries for this business.
     *
     * @return HasMany<ConversationLog>
     */
    public function conversationLogs(): HasMany
    {
        return $this->hasMany(ConversationLog::class);
    }

    public function inboundWebhooks(): HasMany
    {
        return $this->hasMany(InboundWebhook::class);
    }

    public function outboundAttempts(): HasMany
    {
        return $this->hasMany(OutboundMessageAttempt::class);
    }

    public function usageEvents(): HasMany
    {
        return $this->hasMany(UsageEvent::class);
    }

    public function voiceChannels(): HasMany
    {
        return $this->hasMany(VoiceChannel::class);
    }

    public function voiceSessions(): HasMany
    {
        return $this->hasMany(VoiceSession::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(BusinessSubscription::class);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the WhatsApp channel config sub-array, or an empty array
     * when WhatsApp is not configured.
     *
     * @return array<string, mixed>
     */
    public function whatsappConfig(): array
    {
        return $this->channel_config['whatsapp'] ?? [];
    }

    /**
     * Return the Messenger channel config sub-array, or an empty array
     * when Messenger is not configured.
     *
     * @return array<string, mixed>
     */
    public function messengerConfig(): array
    {
        return $this->channel_config['messenger'] ?? [];
    }

    /**
     * Determine whether a given messaging channel is enabled.
     *
     * @param  string  $channel  'whatsapp' or 'messenger'
     */
    public function isChannelEnabled(string $channel): bool
    {
        return (bool) ($this->channel_config[$channel]['enabled'] ?? false);
    }

    /**
     * Determine whether Google Calendar integration is active.
     */
    public function isCalendarEnabled(): bool
    {
        return (bool) ($this->integration_config['google_calendar']['enabled'] ?? false);
    }

    /**
     * Determine whether Google Sheets integration is active.
     */
    public function isSheetsEnabled(): bool
    {
        return (bool) ($this->integration_config['google_sheets']['enabled'] ?? false);
    }

    /**
     * Return the configured LLM provider identifier.
     * Defaults to 'claude' when not explicitly set.
     */
    public function llmProvider(): string
    {
        return $this->ai_config['llm_provider'] ?? 'claude';
    }

    /**
     * Return the AI receptionist's name.
     * Defaults to 'AI Assistant' when not explicitly set.
     */
    public function aiName(): string
    {
        return $this->ai_config['ai_name'] ?? 'AI Assistant';
    }
}
