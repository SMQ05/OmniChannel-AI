<?php

declare(strict_types=1);

namespace App\Services\Channel\Contracts;

use App\Data\ChannelSendResult;

/**
 * Contract for outbound messaging channel services.
 *
 * Each supported channel (WhatsApp, Messenger) implements this
 * interface so the rest of the system can send messages without
 * knowing which platform is in use.
 */
interface ChannelServiceInterface
{
    /**
     * Send a text message to the given platform user.
     *
     * @param  string  $platformUserId  The recipient's platform-specific ID
     *                                  (WhatsApp phone number or Messenger PSID)
     * @param  string  $message         The plain-text message body to deliver
     * @param  array<string, mixed>  $channelConfig  The business's channel config sub-array
     * @param  array<string, mixed>  $context        Additional logging / tracing context
     */
    public function sendMessage(
        string $platformUserId,
        string $message,
        array $channelConfig,
        array $context = [],
    ): ChannelSendResult;
}
