<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Business;
use App\Models\BusinessVoiceChannel;

/**
 * VoiceConfigurationService - Manages voice channel configuration.
 *
 * Provides voice-related readiness checks for launch readiness.
 */
class VoiceConfigurationService
{
    /**
     * Get voice configuration status for a channel.
     *
     * @param  Business  $business
     * @param  string  $channelId
     * @return array<string, mixed>
     */
    public function forChannel(Business $business, string $channelId): array
    {
        // Check if channel exists and is configured
        $channel = BusinessVoiceChannel::query()
            ->where('business_id', $business->id)
            ->where('channel_id', $channelId)
            ->first();

        if ($channel === null) {
            return [
                'error' => 'Channel not found',
                'configured' => false,
            ];
        }

        // Check if channel has a configured provider
        $hasProvider = $channel->provider_driver !== null;
        $hasConfigured = $channel->is_configured ?? false;

        if ($hasProvider && $hasConfigured) {
            return [
                'error' => null,
                'configured' => true,
            ];
        }

        return [
            'error' => 'Channel not fully configured',
            'configured' => false,
        ];
    }
}
