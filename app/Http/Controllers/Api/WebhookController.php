<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Business;
use App\Services\ChannelReadinessService;
use App\Services\Messaging\InboundMessageNormalizer;
use App\Services\Messaging\InboundWebhookRecorder;
use App\Services\Usage\UsageMeteringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use Throwable;

class WebhookController extends Controller
{
    public function __construct(
        private readonly ChannelReadinessService $channelReadinessService,
    ) {}

    public function verify(Request $request, string $slug, string $channel): Response
    {
        $mode      = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token     = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $business = Business::query()
            ->with(['messagingChannels', 'messagingConnections'])
            ->where('slug', $slug)
            ->first();
        $envKey = strtoupper($channel) . '_VERIFY_TOKEN';
        $expectedToken = $business !== null
            ? ($this->channelReadinessService->resolveWebhookConfig($business, $channel)['verify_token'] ?: env($envKey))
            : env($envKey);

        if (
            $mode === 'subscribe'
            && $token !== null
            && $expectedToken !== null
            && hash_equals($expectedToken, $token)
        ) {
            return response($challenge, 200);
        }

        Log::warning('Webhook verification failed.', [
            'slug'      => $slug,
            'channel'   => $channel,
            'env_key'   => $envKey,
            'mode'      => $mode,
            'all_query' => $request->query(),
        ]);

        return response('Verification failed.', 403);
    }

    public function receive(
        Request $request,
        string $slug,
        string $channel,
        InboundMessageNormalizer $normalizer,
        InboundWebhookRecorder $recorder,
        UsageMeteringService $usageMetering,
    ): JsonResponse {
        $business = Business::query()
            ->with(['messagingChannels', 'messagingConnections'])
            ->where('slug', $slug)
            ->first();

        if ($business === null) {
            return response()->json(['error' => 'Business not found.'], 404);
        }

        if (!$business->isChannelEnabled($channel)) {
            return response()->json(['error' => 'Channel not enabled.'], 403);
        }

        $rawBody = $request->getContent();
        $provider = $channel === 'whatsapp'
            ? (string) $this->channelReadinessService->forChannel($business, 'whatsapp')['provider']
            : null;
        $correlationId = (string) Str::uuid();

        Log::withContext([
            'correlation_id' => $correlationId,
            'business_id' => $business->id,
            'business_slug' => $slug,
            'channel' => $channel,
        ]);

        if (!$this->hasValidSignature($request, $business, $channel, $provider, $rawBody)) {
            Log::warning('Webhook HMAC validation failed.');

            return response()->json(['error' => 'Invalid signature.'], 403);
        }

        try {
            $payload = $this->decodePayload($request, $channel, $provider, $rawBody);
        } catch (JsonException|Throwable $exception) {
            Log::warning('Webhook payload decoding failed.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid payload.'], 400);
        }

        $normalized = $normalizer->normalize($payload, $channel, $slug, $provider);

        $webhook = $recorder->record(
            business: $business,
            channel: $channel,
            businessSlug: $slug,
            correlationId: $correlationId,
            payload: $payload,
            messageData: $normalized,
            signatureValid: true,
        );

        Log::info('Webhook received and normalized.', [
            'inbound_webhook_id' => $webhook->id,
            'external_message_id' => $webhook->external_message_id,
            'sender_platform_id' => $webhook->sender_platform_id,
            'message_type' => $webhook->message_type,
            'was_recently_created' => $webhook->wasRecentlyCreated,
        ]);

        if ($webhook->wasRecentlyCreated && $normalized->isMessage()) {
            $usageMetering->record(
                business: $business,
                metric: $channel === 'whatsapp' ? 'whatsapp_conversations' : 'messenger_conversations',
                channel: $channel,
                quantity: 1,
                status: 'received',
                referenceType: $webhook::class,
                referenceId: $webhook->id,
            );

            $usageMetering->record(
                business: $business,
                metric: 'messages_received',
                channel: $channel,
                quantity: 1,
                status: 'received',
                referenceType: $webhook::class,
                referenceId: $webhook->id,
            );
        }

        if (!$webhook->wasRecentlyCreated && in_array($webhook->status, ['dispatched', 'processing', 'processed', 'ignored'], true)) {
            Log::info('Duplicate inbound webhook delivery ignored.', [
                'inbound_webhook_id' => $webhook->id,
                'status' => $webhook->status,
            ]);

            return response()->json(['status' => 'duplicate'], 200);
        }

        if (!$normalized->isMessage()) {
            $webhook->forceFill([
                'status' => 'ignored',
                'processed_at' => now(),
            ])->save();

            return response()->json(['status' => 'ignored'], 200);
        }

        try {
            $dispatch = ProcessIncomingMessage::dispatch($webhook->id);

            if (!app()->runningUnitTests()) {
                $dispatch->afterResponse();
            }

            $webhook->forceFill([
                'queue_connection' => config('kynex.queues.named.webhooks.connection'),
                'queue_name' => config('kynex.queues.named.webhooks.queue'),
                'status' => 'dispatched',
                'dispatched_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $webhook->forceFill([
                'queue_connection' => config('kynex.queues.named.webhooks.connection'),
                'queue_name' => config('kynex.queues.named.webhooks.queue'),
                'status' => 'received',
                'last_error' => $exception->getMessage(),
            ])->save();

            Log::error('Inbound webhook dispatch failed; payload retained for replay.', [
                'inbound_webhook_id' => $webhook->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    private function hasValidSignature(Request $request, Business $business, string $channel, ?string $provider, string $rawBody): bool
    {
        $webhookConfig = $this->channelReadinessService->resolveWebhookConfig($business, $channel);

        if ($channel === 'whatsapp' && $provider === 'twilio') {
            $authToken = (string) ($webhookConfig['signing_secret'] ?? '');
            $signature = $request->header('X-Twilio-Signature', '');

            return $this->isValidTwilioSignature($request, $signature, $authToken);
        }

        $header = $request->header('X-Hub-Signature-256', '');
        $envKey = strtoupper($channel) . '_APP_SECRET';
        $secret = (string) ($webhookConfig['signing_secret'] ?? env($envKey, ''));

        if ($secret === '' || $header === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $header);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(Request $request, string $channel, ?string $provider, string $rawBody): array
    {
        if ($channel === 'whatsapp' && $provider === 'twilio') {
            /** @var array<string, mixed> $payload */
            $payload = $request->all();

            return $payload;
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($rawBody, associative: true, flags: JSON_THROW_ON_ERROR);

        return $payload;
    }

    private function isValidTwilioSignature(Request $request, string $header, string $authToken): bool
    {
        if ($authToken === '' || $header === '') {
            return false;
        }

        $payload = $request->url();
        $params = $request->post();
        ksort($params);

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $payload .= $key . $item;
                }

                continue;
            }

            $payload .= $key . (string) $value;
        }

        $expected = base64_encode(hash_hmac('sha1', $payload, $authToken, true));

        return hash_equals($expected, $header);
    }
}
