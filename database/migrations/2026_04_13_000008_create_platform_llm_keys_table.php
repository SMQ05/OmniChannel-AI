<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-level LLM API keys.
 *
 * Allows super_admin to provide shared API keys for Claude, GPT-4o, and MiniMax
 * so small tenants do not need to supply their own. Each provider has one active
 * key at a time. The `key_value` column stores the raw API key — in production
 * this should be encrypted at rest (Laravel encrypt/decrypt helpers via cast).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_llm_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');        // claude | gpt4o | minimax
            $table->string('label');           // Human-readable name, e.g. "Production Anthropic Key"
            $table->text('key_value');         // Encrypted API key
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['provider', 'is_active']); // Only one active key per provider
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_llm_keys');
    }
};
