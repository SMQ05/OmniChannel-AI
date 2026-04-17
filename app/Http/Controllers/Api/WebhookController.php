<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Jobs\ProcessIncomingMessage;
use App\Models\Business;
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
    public function verify(Request $request, string $slug, string $channel): Response
    {
        $mode      = $request->query('hub_mode') ?? $request->query('hub.mode');
        $token     = $request->query('hub_verify_token') ?? $request->query('hub.verify_token');
        $challenge = $request->query('hub_challenge') ?? $request->query('hub.challenge');

        $business      = Business::where('slug', $slug)->first();
        $envKey        = strtoupper($channel) . '_VERIFY_TOKEN';
        $expectedToken = $business?->channel_config[$channel]['verify_token'] ?: env($envKey);

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
        $business = Business::where('slug', $slug)->first();

        if ($business === null) {
            return response()->json(['error' => 'Business not found.'], 404);
        }

        if (!$business->isChannelEnabled($channel)) {
            return response()->json(['error' => 'Channel not enabled.'], 403);
        }

        $rawBody = $request->getContent();
        $header = $request->header('X-Hub-Signature-256', '');
        $envKey = strtoupper($channel) . '_APP_SECRET';
        $secret = $business->channel_config[$channel]['app_secret'] ?? env($envKey, '');
        $correlationId = (string) Str::uuid();

        Log::withContext([
            'correlation_id' => $correlationId,
            'business_id' => $business->id,
            'business_slug' => $slug,
            'channel' => $channel,
        ]);

        if (!$this->isValidSignature($rawBody, $header, $secret)) {
            Log::warning('Webhook HMAC validation failed.');

            return response()->json(['error' => 'Invalid signature.'], 403);
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($rawBody, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('Webhook payload JSON decoding failed.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['error' => 'Invalid payload.'], 400);
        }

        $normalized = $normalizer->normalize($payload, $channel, $slug);

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
            ProcessIncomingMessage::dispatch($webhook->id);

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

    private function isValidSignature(string $rawBody, string $header, string $secret): bool
    {
        if ($secret === '' || $header === '') {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $header);
    }
}
