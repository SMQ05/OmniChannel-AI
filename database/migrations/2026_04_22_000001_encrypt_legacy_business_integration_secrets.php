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
        Schema::table('businesses', function (Blueprint $table): void {
            $table->longText('integration_secrets')->nullable()->after('integration_config');
        });

        $timestamp = now();

        DB::table('businesses')
            ->select(['id', 'channel_config', 'integration_config', 'integration_secrets'])
            ->orderBy('id')
            ->chunkById(100, function ($businesses) use ($timestamp): void {
                foreach ($businesses as $business) {
                    $channelConfig = $this->decodeJsonColumn($business->channel_config);
                    $integrationConfig = $this->decodeJsonColumn($business->integration_config);
                    $integrationSecrets = $this->decodeEncryptedJsonColumn($business->integration_secrets);

                    $integrationSecrets = $this->migrateGoogleSecrets($integrationConfig, $integrationSecrets);
                    $channelConfig = $this->migrateMessagingSecrets(
                        businessId: (int) $business->id,
                        channelConfig: $channelConfig,
                        timestamp: $timestamp,
                    );

                    DB::table('businesses')
                        ->where('id', $business->id)
                        ->update([
                            'channel_config' => json_encode($channelConfig, JSON_THROW_ON_ERROR),
                            'integration_config' => json_encode($integrationConfig, JSON_THROW_ON_ERROR),
                            'integration_secrets' => $integrationSecrets === []
                                ? null
                                : Crypt::encryptString(json_encode($integrationSecrets, JSON_THROW_ON_ERROR)),
                        ]);
                }
            });
    }

    public function down(): void
    {
        $timestamp = now();

        DB::table('businesses')
            ->select(['id', 'channel_config', 'integration_config', 'integration_secrets'])
            ->orderBy('id')
            ->chunkById(100, function ($businesses) use ($timestamp): void {
                foreach ($businesses as $business) {
                    $channelConfig = $this->decodeJsonColumn($business->channel_config);
                    $integrationConfig = $this->decodeJsonColumn($business->integration_config);
                    $integrationSecrets = $this->decodeEncryptedJsonColumn($business->integration_secrets);

                    $integrationConfig = $this->restoreGoogleSecrets($integrationConfig, $integrationSecrets);
                    $channelConfig = $this->restoreMessagingSecrets(
                        businessId: (int) $business->id,
                        channelConfig: $channelConfig,
                        timestamp: $timestamp,
                    );

                    DB::table('businesses')
                        ->where('id', $business->id)
                        ->update([
                            'channel_config' => json_encode($channelConfig, JSON_THROW_ON_ERROR),
                            'integration_config' => json_encode($integrationConfig, JSON_THROW_ON_ERROR),
                            'integration_secrets' => null,
                        ]);
                }
            });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('integration_secrets');
        });
    }

    /**
     * @param  array<string, mixed>  $integrationConfig
     * @param  array<string, mixed>  $integrationSecrets
     * @return array<string, mixed>
     */
    private function migrateGoogleSecrets(array &$integrationConfig, array $integrationSecrets): array
    {
        $credentials = $this->arrayValue($integrationConfig['google_credentials'] ?? null);
        $secretCredentials = $this->arrayValue($integrationSecrets['google_credentials'] ?? null);

        if ($this->filledValue($credentials['client_secret'] ?? null) && !$this->filledValue($secretCredentials['client_secret'] ?? null)) {
            $secretCredentials['client_secret'] = (string) $credentials['client_secret'];
        }

        if ($secretCredentials !== []) {
            $integrationSecrets['google_credentials'] = $secretCredentials;
        }

        unset($credentials['client_secret']);
        $integrationConfig['google_credentials'] = $this->onlyFilled($credentials);

        if (($integrationConfig['google_credentials'] ?? []) === []) {
            unset($integrationConfig['google_credentials']);
        }

        foreach (['google_calendar', 'google_sheets'] as $service) {
            $serviceConfig = $this->arrayValue($integrationConfig[$service] ?? null);
            $serviceSecrets = $this->arrayValue($integrationSecrets[$service] ?? null);
            $legacyToken = $this->onlyFilled($this->arrayValue($serviceConfig['token'] ?? null));

            if ($legacyToken !== []) {
                $serviceSecrets['token'] = array_merge(
                    $this->arrayValue($serviceSecrets['token'] ?? null),
                    $legacyToken,
                );
            }

            if ($serviceSecrets !== []) {
                $integrationSecrets[$service] = $serviceSecrets;
            }

            unset($serviceConfig['token']);
            $integrationConfig[$service] = $serviceConfig;
        }

        return $integrationSecrets;
    }

    /**
     * @param  array<string, mixed>  $channelConfig
     * @return array<string, mixed>
     */
    private function migrateMessagingSecrets(int $businessId, array $channelConfig, \Illuminate\Support\Carbon $timestamp): array
    {
        foreach (['whatsapp', 'messenger'] as $channel) {
            $legacy = $this->arrayValue($channelConfig[$channel] ?? null);

            if ($legacy === []) {
                continue;
            }

            [$provider, $credentials, $runtimeConfig] = $this->legacyMessagingPayload($channel, $legacy);

            if ($credentials !== [] || $runtimeConfig !== []) {
                $this->upsertMessagingConnection(
                    businessId: $businessId,
                    channel: $channel,
                    provider: $provider,
                    credentials: $credentials,
                    runtimeConfig: $runtimeConfig,
                    timestamp: $timestamp,
                );
            }

            $channelConfig[$channel] = $this->safeLegacyMessagingConfig($channel, $legacy);
        }

        return $channelConfig;
    }

    /**
     * @param  array<string, mixed>  $integrationConfig
     * @param  array<string, mixed>  $integrationSecrets
     * @return array<string, mixed>
     */
    private function restoreGoogleSecrets(array $integrationConfig, array $integrationSecrets): array
    {
        $credentials = $this->arrayValue($integrationConfig['google_credentials'] ?? null);
        $secretCredentials = $this->arrayValue($integrationSecrets['google_credentials'] ?? null);

        if ($this->filledValue($secretCredentials['client_secret'] ?? null)) {
            $credentials['client_secret'] = (string) $secretCredentials['client_secret'];
        }

        if ($credentials !== []) {
            $integrationConfig['google_credentials'] = $credentials;
        }

        foreach (['google_calendar', 'google_sheets'] as $service) {
            $serviceSecrets = $this->arrayValue($integrationSecrets[$service] ?? null);
            $token = $this->arrayValue($serviceSecrets['token'] ?? null);

            if ($token === []) {
                continue;
            }

            $serviceConfig = $this->arrayValue($integrationConfig[$service] ?? null);
            $serviceConfig['token'] = $token;
            $integrationConfig[$service] = $serviceConfig;
        }

        return $integrationConfig;
    }

    /**
     * @param  array<string, mixed>  $channelConfig
     * @return array<string, mixed>
     */
    private function restoreMessagingSecrets(int $businessId, array $channelConfig, \Illuminate\Support\Carbon $timestamp): array
    {
        foreach (['whatsapp', 'messenger'] as $channel) {
            $connection = DB::table('messaging_channel_connections')
                ->where('business_id', $businessId)
                ->where('channel', $channel)
                ->first();

            if ($connection === null) {
                continue;
            }

            $credentials = $this->decodeEncryptedJsonColumn($connection->credentials);
            $runtimeConfig = $this->decodeEncryptedJsonColumn($connection->runtime_config);
            $provider = (string) $connection->provider;

            $channelConfig[$channel] = array_merge(
                $this->arrayValue($channelConfig[$channel] ?? null),
                $credentials,
                $runtimeConfig,
                ['provider' => $provider],
            );

            DB::table('messaging_channel_connections')
                ->where('id', $connection->id)
                ->update(['updated_at' => $timestamp]);
        }

        return $channelConfig;
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array{0: string, 1: array<string, mixed>, 2: array<string, mixed>}
     */
    private function legacyMessagingPayload(string $channel, array $legacy): array
    {
        if ($channel === 'whatsapp') {
            $provider = (string) ($legacy['provider'] ?? 'meta_cloud');

            return [
                $provider,
                $this->onlyFilled([
                    'access_token' => $legacy['access_token'] ?? null,
                    'verify_token' => $legacy['verify_token'] ?? null,
                    'app_secret' => $legacy['app_secret'] ?? null,
                    'twilio_account_sid' => $legacy['twilio_account_sid'] ?? null,
                    'twilio_auth_token' => $legacy['twilio_auth_token'] ?? null,
                ]),
                $this->onlyFilled([
                    'phone_number_id' => $legacy['phone_number_id'] ?? null,
                    'twilio_from_number' => $legacy['twilio_from_number'] ?? null,
                ]),
            ];
        }

        return [
            'meta',
            $this->onlyFilled([
                'access_token' => $legacy['access_token'] ?? null,
                'verify_token' => $legacy['verify_token'] ?? null,
                'app_secret' => $legacy['app_secret'] ?? null,
            ]),
            $this->onlyFilled([
                'page_id' => $legacy['page_id'] ?? null,
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $legacy
     * @return array<string, mixed>
     */
    private function safeLegacyMessagingConfig(string $channel, array $legacy): array
    {
        return match ($channel) {
            'whatsapp' => $this->onlyFilled([
                'enabled' => (bool) ($legacy['enabled'] ?? false),
                'provider' => $legacy['provider'] ?? 'meta_cloud',
                'phone_number_id' => $legacy['phone_number_id'] ?? null,
                'twilio_from_number' => $legacy['twilio_from_number'] ?? null,
            ]),
            default => $this->onlyFilled([
                'enabled' => (bool) ($legacy['enabled'] ?? false),
                'provider' => 'meta',
                'page_id' => $legacy['page_id'] ?? null,
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $runtimeConfig
     */
    private function upsertMessagingConnection(
        int $businessId,
        string $channel,
        string $provider,
        array $credentials,
        array $runtimeConfig,
        \Illuminate\Support\Carbon $timestamp,
    ): void {
        $existing = DB::table('messaging_channel_connections')
            ->where('business_id', $businessId)
            ->where('channel', $channel)
            ->first();

        $mergedCredentials = array_merge(
            $existing !== null ? $this->decodeEncryptedJsonColumn($existing->credentials) : [],
            $credentials,
        );
        $mergedRuntimeConfig = array_merge(
            $existing !== null ? $this->decodeEncryptedJsonColumn($existing->runtime_config) : [],
            $runtimeConfig,
        );

        $status = $this->messagingConnectionStatus($channel, $provider, $mergedCredentials, $mergedRuntimeConfig);

        DB::table('messaging_channel_connections')->updateOrInsert(
            ['business_id' => $businessId, 'channel' => $channel],
            [
                'provider' => $existing?->provider ?? $provider,
                'status' => $status,
                'credentials' => Crypt::encryptString(json_encode($mergedCredentials, JSON_THROW_ON_ERROR)),
                'runtime_config' => Crypt::encryptString(json_encode($mergedRuntimeConfig, JSON_THROW_ON_ERROR)),
                'connected_at' => $status === 'connected' ? ($existing?->connected_at ?? $timestamp) : ($existing?->connected_at ?? null),
                'created_at' => $existing?->created_at ?? $timestamp,
                'updated_at' => $timestamp,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $runtimeConfig
     */
    private function messagingConnectionStatus(
        string $channel,
        string $provider,
        array $credentials,
        array $runtimeConfig,
    ): string {
        return match ($channel) {
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
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonColumn(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeEncryptedJsonColumn(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        $decrypted = Crypt::decryptString($value);
        $decoded = json_decode($decrypted, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function onlyFilled(array $values): array
    {
        return array_filter(
            $values,
            fn (mixed $value): bool => $this->filledValue($value) || is_bool($value),
        );
    }

    private function filledValue(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : filled($value);
    }
};
