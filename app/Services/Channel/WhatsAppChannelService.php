<?php

declare(strict_types=1);

namespace App\Services\Channel;

use App\Data\ChannelSendResult;
use App\Exceptions\ChannelConfigurationException;
use App\Exceptions\ChannelDeliveryException;
use App\Services\Channel\Contracts\ChannelServiceInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends outbound messages via the WhatsApp Business Cloud API (Meta).
 *
 * Endpoint:
 *   POST https://graph.facebook.com/v19.0/{phone_number_id}/messages
 *
 * Authentication:
 *   Bearer token from channel_config.whatsapp.access_token.
 *
 * Errors are surfaced as typed exceptions so queue jobs can classify
 * transient vs permanent delivery failures and retry safely.
 */
class WhatsAppChannelService implements ChannelServiceInterface
{
    private const API_BASE    = 'https://graph.facebook.com';
    private const API_VERSION = 'v19.0';

    /**
     * Send a plain-text WhatsApp message to the given recipient.
     *
     * @param  string               $platformUserId  The recipient's WhatsApp phone number (E.164 format)
     * @param  string               $message         The message text (max 4096 chars for WhatsApp)
     * @param  array<string, mixed> $channelConfig   The whatsapp sub-array from businesses.channel_config
     * @param  array<string, mixed> $context         Additional tracing context
     */
    public function sendMessage(
        string $platformUserId,
        string $message,
        array $channelConfig,
        array $context = [],
    ): ChannelSendResult {
        $phoneNumberId = (string) ($channelConfig['phone_number_id'] ?? '');
        $accessToken   = (string) ($channelConfig['access_token'] ?? '');

        if ($phoneNumberId === '' || $accessToken === '') {
            throw new ChannelConfigurationException(
                'WhatsAppChannelService: missing phone_number_id or access_token.'
            );
        }

        $url = sprintf(
            '%s/%s/%s/messages',
            self::API_BASE,
            self::API_VERSION,
            $phoneNumberId,
        );

        try {
            $response = Http::withToken($accessToken)
                ->timeout(15)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type'    => 'individual',
                    'to'                => $platformUserId,
                    'type'              => 'text',
                    'text'              => [
                        'preview_url' => false,
                        'body'        => $message,
                    ],
                ]);

            if ($response->failed()) {
                Log::error('WhatsAppChannelService: API returned non-2xx response.', array_merge($context, [
                    'platform_user_id' => $platformUserId,
                    'status'           => $response->status(),
                    'body'             => $response->body(),
                ]));

                throw new ChannelDeliveryException(
                    message: 'WhatsAppChannelService: Meta API returned a non-success response.',
                    transient: $response->serverError() || in_array($response->status(), [408, 409, 425, 429], true),
                    statusCode: $response->status(),
                    responseBody: $response->body(),
                );
            }

            $decoded = $response->json();

            return new ChannelSendResult(
                statusCode: $response->status(),
                providerMessageId: $decoded['messages'][0]['id'] ?? null,
                rawBody: $response->body(),
                decodedBody: is_array($decoded) ? $decoded : null,
            );
        } catch (ConnectionException $e) {
            Log::error('WhatsAppChannelService: HTTP connection error.', array_merge($context, [
                'platform_user_id' => $platformUserId,
                'error'            => $e->getMessage(),
            ]));

            throw new ChannelDeliveryException(
                message: 'WhatsAppChannelService: HTTP connection error.',
                transient: true,
                responseBody: $e->getMessage(),
                previous: $e,
            );
        }
    }
}
