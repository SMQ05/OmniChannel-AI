<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_messaging_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->boolean('is_enabled')->default(false);
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('enabled_at')->nullable();
            $table->timestampTz('disabled_at')->nullable();
            $table->foreignId('disabled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disable_reason', 255)->nullable();
            $table->timestampsTz();

            $table->unique(['business_id', 'channel'], 'business_messaging_channels_business_channel_unique');
        });

        Schema::create('messaging_channel_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->string('provider', 32);
            $table->string('status', 32)->default('incomplete');
            $table->longText('credentials')->nullable();
            $table->longText('runtime_config')->nullable();
            $table->timestampTz('connected_at')->nullable();
            $table->timestampTz('last_tested_at')->nullable();
            $table->string('last_test_status', 32)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestampTz('disconnected_at')->nullable();
            $table->foreignId('disconnected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disconnect_reason', 255)->nullable();
            $table->text('last_error')->nullable();
            $table->timestampsTz();

            $table->unique(['business_id', 'channel'], 'messaging_channel_connections_business_channel_unique');
        });

        $timestamp = now();

        DB::table('businesses')
            ->select(['id', 'channel_config'])
            ->orderBy('id')
            ->chunkById(100, function ($businesses) use ($timestamp): void {
                foreach ($businesses as $business) {
                    $config = json_decode((string) $business->channel_config, true);

                    if (!is_array($config)) {
                        continue;
                    }

                    foreach (['whatsapp', 'messenger'] as $channel) {
                        $legacy = $config[$channel] ?? null;

                        if (!is_array($legacy)) {
                            continue;
                        }

                        $enabled = (bool) ($legacy['enabled'] ?? false);

                        if ($enabled || $legacy !== []) {
                            DB::table('business_messaging_channels')->updateOrInsert(
                                ['business_id' => $business->id, 'channel' => $channel],
                                [
                                    'is_enabled' => $enabled,
                                    'approved_at' => $enabled ? $timestamp : null,
                                    'enabled_at' => $enabled ? $timestamp : null,
                                    'disabled_at' => $enabled ? null : $timestamp,
                                    'disable_reason' => $enabled ? null : 'Backfilled from legacy channel_config.',
                                    'created_at' => $timestamp,
                                    'updated_at' => $timestamp,
                                ],
                            );
                        }

                        [$provider, $credentials, $runtimeConfig] = match ($channel) {
                            'whatsapp' => [
                                (string) ($legacy['provider'] ?? 'meta_cloud'),
                                array_filter([
                                    'access_token' => $legacy['access_token'] ?? null,
                                    'verify_token' => $legacy['verify_token'] ?? null,
                                    'app_secret' => $legacy['app_secret'] ?? null,
                                    'twilio_account_sid' => $legacy['twilio_account_sid'] ?? null,
                                    'twilio_auth_token' => $legacy['twilio_auth_token'] ?? null,
                                ], static fn (mixed $value): bool => filled($value)),
                                array_filter([
                                    'phone_number_id' => $legacy['phone_number_id'] ?? null,
                                    'twilio_from_number' => $legacy['twilio_from_number'] ?? null,
                                ], static fn (mixed $value): bool => filled($value)),
                            ],
                            default => [
                                'meta',
                                array_filter([
                                    'access_token' => $legacy['access_token'] ?? null,
                                    'verify_token' => $legacy['verify_token'] ?? null,
                                    'app_secret' => $legacy['app_secret'] ?? null,
                                ], static fn (mixed $value): bool => filled($value)),
                                array_filter([
                                    'page_id' => $legacy['page_id'] ?? null,
                                ], static fn (mixed $value): bool => filled($value)),
                            ],
                        };

                        if ($credentials === [] && $runtimeConfig === []) {
                            continue;
                        }

                        $status = match ($channel) {
                            'whatsapp' => $provider === 'twilio'
                                ? (
                                    isset($credentials['twilio_account_sid'], $credentials['twilio_auth_token'], $runtimeConfig['twilio_from_number'])
                                        ? 'connected'
                                        : 'incomplete'
                                )
                                : (
                                    isset($credentials['access_token'], $credentials['verify_token'], $credentials['app_secret'], $runtimeConfig['phone_number_id'])
                                        ? 'connected'
                                        : 'incomplete'
                                ),
                            default => isset($credentials['access_token'], $credentials['verify_token'], $credentials['app_secret'], $runtimeConfig['page_id'])
                                ? 'connected'
                                : 'incomplete',
                        };

                        DB::table('messaging_channel_connections')->updateOrInsert(
                            ['business_id' => $business->id, 'channel' => $channel],
                            [
                                'provider' => $provider,
                                'status' => $status,
                                'credentials' => Crypt::encryptString(json_encode($credentials, JSON_THROW_ON_ERROR)),
                                'runtime_config' => Crypt::encryptString(json_encode($runtimeConfig, JSON_THROW_ON_ERROR)),
                                'connected_at' => $status === 'connected' ? $timestamp : null,
                                'created_at' => $timestamp,
                                'updated_at' => $timestamp,
                            ],
                        );
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('messaging_channel_connections');
        Schema::dropIfExists('business_messaging_channels');
    }
};
