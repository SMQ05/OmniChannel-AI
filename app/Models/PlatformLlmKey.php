<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-level LLM API key managed by super_admin.
 *
 * Tenants that do not provide their own LLM API key in ai_config
 * will fall back to the active platform key for their chosen provider.
 * The `key_value` is stored encrypted via the `encrypted` cast so it
 * is never readable in plain text from the database.
 *
 * @property int    $id
 * @property string $provider    'claude' | 'gpt4o' | 'openrouter' | 'minimax'
 * @property string $label
 * @property string $key_value   Decrypted at runtime by Eloquent cast
 * @property bool   $is_active
 */
#[\App\Models\Attributes\Table('platform_llm_keys')]
class PlatformLlmKey extends Model
{
    /**
     * Mass-assignable attributes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'provider',
        'label',
        'key_value',
        'is_active',
    ];

    /**
     * Attribute casts.
     *
     * key_value is encrypted at rest — Laravel automatically encrypts on save
     * and decrypts on read. The value in the database is unreadable without
     * the application key.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key_value' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Retrieve the active platform key for the given LLM provider.
     *
     * Returns null if no active platform key is configured — in that case the
     * calling code should fall back to requiring a tenant-level key.
     *
     * @param  string  $provider  'claude' | 'gpt4o' | 'openrouter' | 'minimax'
     * @return static|null
     */
    public static function activeFor(string $provider): ?static
    {
        return static::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();
    }
}
