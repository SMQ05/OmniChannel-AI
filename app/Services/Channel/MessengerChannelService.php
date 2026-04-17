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
 * Sends outbound messages via the Messenger Platform (Meta).
 *
 * Endpoint:
 *   POST https://graph.facebook.com/v19.0/me/messages
 *
 * Authentication:
 *   access_token passed as a query parameter (Messenger Platform requirement).
 *
 * Errors are surfaced as typed exceptions so queue jobs can classify
 * transient vs permanent delivery failures and retry safely.
 */
class MessengerChannelService implements ChannelServiceInterface
{
    private const API_BASE    = 'https://graph.facebook.com';
    private const API_VERSION = 'v19.0';

    /**
     * Send a plain-text Messenger message to the given PSID.
     *
     * @param  string               $platformUserId  The recipient's Messenger Page-Scoped ID (PSID)
     * @param  string               $message         The message text (max 2000 chars for Messenger)
     * @param  array<string, mixed> $channelConfig   The messenger sub-array from businesses.channel_config
     * @param  array<string, mixed> $context         Additional tracing context
     */
    public function sendMessage(
        string $platformUserId,
        string $message,
        array $channelConfig,
        array $context = [],
    ): ChannelSendResult {
        $accessToken = (string) ($channelConfig['access_token'] ?? '');

        if ($accessToken === '') {
            throw new ChannelConfigurationException('MessengerChannelService: missing access_token.');
        }

        $url = sprintf(
            '%s/%s/me/messages',
            self::API_BASE,
            self::API_VERSION,
        );

        try {
            $response = Http::timeout(15)
                ->post($url, [
                    'access_token' => $accessToken,
                    'recipient'    => ['id' => $platformUserId],
                    'message'      => ['text' => $message],
                    'messaging_type' => 'RESPONSE',
                ]);

            if ($response->failed()) {
                Log::error('MessengerChannelService: API returned non-2xx response.', array_merge($context, [
                    'platform_user_id' => $platformUserId,
                    'status'           => $response->status(),
                    'body'             => $response->body(),
                ]));

                throw new ChannelDeliveryException(
                    message: 'MessengerChannelService: Meta API returned a non-success response.',
                    transient: $response->serverError() || in_array($response->status(), [408, 409, 425, 429], true),
                    statusCode: $response->status(),
                    responseBody: $response->body(),
                );
            }

            $decoded = $response->json();

            return new ChannelSendResult(
                statusCode: $response->status(),
                providerMessageId: $decoded['message_id'] ?? null,
                rawBody: $response->body(),
                decodedBody: is_array($decoded) ? $decoded : null,
            );
        } catch (ConnectionException $e) {
            Log::error('MessengerChannelService: HTTP connection error.', array_merge($context, [
                'platform_user_id' => $platformUserId,
                'error'            => $e->getMessage(),
            ]));

            throw new ChannelDeliveryException(
                message: 'MessengerChannelService: HTTP connection error.',
                transient: true,
                responseBody: $e->getMessage(),
                previous: $e,
            );
        }
    }
}
