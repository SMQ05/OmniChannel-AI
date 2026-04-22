<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            // AI policy columns - these extend existing ai_config
            // Do not imply centralization - runtime still reads from ai_config

            // Rate limiting
            $table->integer('ai_rate_limit_per_hour')->default(100)->after('ai_config');
            $table->integer('ai_rate_limit_per_day')->default(1000)->after('ai_rate_limit_per_hour');

            // Guardrails
            $table->boolean('ai_content_safety_enabled')->default(true)->after('ai_rate_limit_per_day');
            $table->boolean('ai_pii_detection_enabled')->default(true)->after('ai_content_safety_enabled');
            $table->boolean('ai_hallucination_guard_enabled')->default(true)->after('ai_pii_detection_enabled');

            // Feature flags
            $table->boolean('ai_voice_agent_enabled')->default(false)->after('ai_hallucination_guard_enabled');
            $table->boolean('ai_email_agent_enabled')->default(false)->after('ai_voice_agent_enabled');
            $table->boolean('ai_chat_only_enabled')->default(false)->after('ai_email_agent_enabled');

            // Fallback behavior
            $table->string('ai_fallback_provider', 32)->nullable()->after('ai_chat_only_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn([
                'ai_rate_limit_per_hour',
                'ai_rate_limit_per_day',
                'ai_content_safety_enabled',
                'ai_pii_detection_enabled',
                'ai_hallucination_guard_enabled',
                'ai_voice_agent_enabled',
                'ai_email_agent_enabled',
                'ai_chat_only_enabled',
                'ai_fallback_provider',
            ]);
        });
    }
};
