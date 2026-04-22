<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessMessagingChannel;
use App\Models\MessagingChannelConnection;

class ChannelReadinessService
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function forBusiness(Business $business): array
    {
        return [
            'whatsapp' => $this->forChannel($business, 'whatsapp'),
            'messenger' => $this->forChannel($business, 'messenger'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forChannel(Business $business, string $channel): array
    {
        $businessState = $this->businessChannel($business, $channel);
        $connection = $this->connection($business, $channel);
        $legacy = $this->legacyChannelConfig($business, $channel);

        $provider = $connection?->provider
            ?? ($channel === 'messenger' ? 'meta' : (string) ($legacy['provider'] ?? 'meta_cloud'));

        $resolved = $connection !== null
            ? $this->resolvedFromConnection($connection)
            : $this->resolvedFromLegacy($channel, $legacy);

        $enabled = $businessState?->is_enabled ?? (bool) ($legacy['enabled'] ?? false);
        $connected = $this->connectionComplete($channel, $provider, $resolved['credentials'], $resolved['runtime_config']);
        $ready = $enabled && $connected;
        $errors = [];

        if ($enabled && !$connected) {
            $errors[] = 'Channel is enabled but the managed connection is incomplete.';
        }

        if ($connection?->status === 'disconnected') {
            $errors[] = 'Connection was explicitly disconnected from the admin control plane.';
        }

        return [
            'channel' => $channel,
            'provider' => $provider,
            'provider_label' => $this->providerLabel($channel, $provider),
            'enabled' => $enabled,
            'connected' => $connected,
            'ready' => $ready,
            'status' => $this->status($enabled, $connected, $connection?->status),
            'errors' => $errors,
            'business_state_source' => $businessState !== null ? 'business_messaging_channels' : 'legacy_channel_config',
            'connection_source' => $connection !== null ? 'messaging_channel_connections' : 'legacy_channel_config',
            'business_state' => $businessState,
            'connection' => $connection,
            'connection_status' => $connection?->status,
            'approved_at' => $businessState?->approved_at,
            'enabled_at' => $businessState?->enabled_at,
            'disabled_at' => $businessState?->disabled_at,
            'disable_reason' => $businessState?->disable_reason,
            'last_tested_at' => $connection?->last_tested_at,
            'last_test_status' => $connection?->last_test_status,
            'last_test_message' => $connection?->last_test_message,
            'summary' => $this->summary($channel, $provider, $resolved['runtime_config']),
            'runtime_config' => $resolved['runtime_config'],
            'credentials' => $resolved['credentials'],
            'verify_token' => $resolved['credentials']['verify_token'] ?? null,
            'signing_secret' => $provider === 'twilio'
                ? ($resolved['credentials']['twilio_auth_token'] ?? null)
                : ($resolved['credentials']['app_secret'] ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveOutboundConfig(Business $business, string $channel): array
    {
        $state = $this->forChannel($business, $channel);

        return [
            'provider' => $state['provider'],
            ...$state['runtime_config'],
            ...$state['credentials'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveWebhookConfig(Business $business, string $channel): array
    {
        $state = $this->forChannel($business, $channel);

        return [
            'enabled' => $state['enabled'],
            'provider' => $state['provider'],
            'verify_token' => $state['verify_token'],
            'signing_secret' => $state['signing_secret'],
        ];
    }

    public function connectionComplete(
        string $channel,
        string $provider,
        array $credentials,
        array $runtimeConfig,
    ): bool {
        if ($channel === 'whatsapp' && $provider === 'twilio') {
            return $this->filled($credentials['twilio_account_sid'] ?? null)
                && $this->filled($credentials['twilio_auth_token'] ?? null)
                && $this->filled($runtimeConfig['twilio_from_number'] ?? null);
        }

        if ($channel === 'whatsapp') {
            return $this->filled($runtimeConfig['phone_number_id'] ?? null)
                && $this->filled($credentials['access_token'] ?? null)
                && $this->filled($credentials['verify_token'] ?? null)
                && $this->filled($credentials['app_secret'] ?? null);
        }

        return $this->filled($runtimeConfig['page_id'] ?? null)
            && $this->filled($credentials['access_token'] ?? null)
            && $this->filled($credentials['verify_token'] ?? null)
            && $this->filled($credentials['app_secret'] ?? null);
    }

    public function businessChannel(Business $business, string $channel): ?BusinessMessagingChannel
    {
        return $business->relationLoaded('messagingChannels')
            ? $business->messagingChannels->firstWhere('channel', $channel)
            : $business->messagingChannels()->where('channel', $channel)->first();
    }

    public function connection(Business $business, string $channel): ?MessagingChannelConnection
    {
        return $business->relationLoaded('messagingConnections')
            ? $business->messagingConnections->firstWhere('channel', $channel)
            : $business->messagingConnections()->where('channel', $channel)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyChannelConfig(Business $business, string $channel): array
    {
        $legacy = $business->channel_config[$channel] ?? [];

        return is_array($legacy) ? $legacy : [];
    }

    /**
     * @return array{credentials: array<string, mixed>, runtime_config: array<string, mixed>}
     */
    private function resolvedFromConnection(MessagingChannelConnection $connection): array
    {
        return [
            'credentials' => is_array($connection->credentials) ? $connection->credentials : [],
            'runtime_config' => is_array($connection->runtime_config) ? $connection->runtime_config : [],
        ];
    }

    /**
     * @return array{credentials: array<string, mixed>, runtime_config: array<string, mixed>}
     */
    private function resolvedFromLegacy(string $channel, array $legacy): array
    {
        if ($channel === 'whatsapp') {
            return [
                'credentials' => array_filter([
                    'access_token' => $legacy['access_token'] ?? null,
                    'verify_token' => $legacy['verify_token'] ?? null,
                    'app_secret' => $legacy['app_secret'] ?? null,
                    'twilio_account_sid' => $legacy['twilio_account_sid'] ?? null,
                    'twilio_auth_token' => $legacy['twilio_auth_token'] ?? null,
                ], fn (mixed $value): bool => $this->filled($value)),
                'runtime_config' => array_filter([
                    'phone_number_id' => $legacy['phone_number_id'] ?? null,
                    'twilio_from_number' => $legacy['twilio_from_number'] ?? null,
                ], fn (mixed $value): bool => $this->filled($value)),
            ];
        }

        return [
            'credentials' => array_filter([
                'access_token' => $legacy['access_token'] ?? null,
                'verify_token' => $legacy['verify_token'] ?? null,
                'app_secret' => $legacy['app_secret'] ?? null,
            ], fn (mixed $value): bool => $this->filled($value)),
            'runtime_config' => array_filter([
                'page_id' => $legacy['page_id'] ?? null,
            ], fn (mixed $value): bool => $this->filled($value)),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private function summary(string $channel, string $provider, array $runtimeConfig): array
    {
        if ($channel === 'whatsapp') {
            return [
                'identifier' => $provider === 'twilio'
                    ? ($runtimeConfig['twilio_from_number'] ?? null)
                    : ($runtimeConfig['phone_number_id'] ?? null),
                'webhook_mode' => $provider === 'twilio' ? 'Twilio signature' : 'Meta webhook signature',
            ];
        }

        return [
            'identifier' => $runtimeConfig['page_id'] ?? null,
            'webhook_mode' => 'Meta webhook signature',
        ];
    }

    private function providerLabel(string $channel, string $provider): string
    {
        return match ("{$channel}:{$provider}") {
            'whatsapp:meta_cloud' => 'Meta WhatsApp Cloud',
            'whatsapp:twilio' => 'Twilio WhatsApp (Legacy)',
            default => 'Meta Messenger',
        };
    }

    private function status(bool $enabled, bool $connected, ?string $connectionStatus): string
    {
        if ($connectionStatus === 'disconnected') {
            return 'disconnected';
        }

        if ($enabled && $connected) {
            return 'live';
        }

        if ($connected) {
            return 'connected';
        }

        if ($enabled) {
            return 'blocked';
        }

        return 'inactive';
    }

    private function filled(mixed $value): bool
    {
        return is_string($value) ? trim($value) !== '' : filled($value);
    }
}
