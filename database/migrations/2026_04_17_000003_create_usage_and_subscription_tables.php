<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('included_quotas')->nullable();
            $table->json('feature_flags')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('business_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('status', 32)->default('trial');
            $table->json('included_quotas')->nullable();
            $table->json('overage_counters')->nullable();
            $table->json('feature_flags')->nullable();
            $table->decimal('warn_at_ratio', 5, 2)->default(0.80);
            $table->boolean('enforce_limits')->default(false);
            $table->boolean('admin_override')->default(false);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamps();

            $table->unique('business_id');
        });

        Schema::create('usage_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('metric', 80);
            $table->string('channel', 32)->nullable();
            $table->decimal('quantity', 12, 4)->default(0);
            $table->string('status', 32)->default('recorded');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'metric', 'recorded_at']);
        });

        DB::table('plans')->insert([
            [
                'code' => 'trial',
                'name' => 'Trial',
                'description' => 'Default trial plan',
                'included_quotas' => json_encode([
                    'messages_received' => 500,
                    'messages_sent' => 500,
                    'llm_tokens_estimated' => 100000,
                    'reminders_sent' => 100,
                    'voice_minutes' => 0,
                ], JSON_THROW_ON_ERROR),
                'feature_flags' => json_encode([
                    'whatsapp' => true,
                    'messenger' => true,
                    'voice_agent' => false,
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'pro',
                'name' => 'Pro',
                'description' => 'Production SaaS plan',
                'included_quotas' => json_encode([
                    'messages_received' => 5000,
                    'messages_sent' => 5000,
                    'llm_tokens_estimated' => 1000000,
                    'reminders_sent' => 1000,
                    'voice_minutes' => 300,
                ], JSON_THROW_ON_ERROR),
                'feature_flags' => json_encode([
                    'whatsapp' => true,
                    'messenger' => true,
                    'voice_agent' => true,
                ], JSON_THROW_ON_ERROR),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');
        Schema::dropIfExists('business_subscriptions');
        Schema::dropIfExists('plans');
    }
};
