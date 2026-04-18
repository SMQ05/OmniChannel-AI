<?php

declare(strict_types=1);

use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\Internal\VoiceSessionController;
use App\Http\Controllers\Api\Internal\VoiceToolController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Webhook endpoints for Meta (WhatsApp Business API + Messenger Platform).
|
| Each channel has two routes per tenant slug:
|  GET  — Meta webhook verification (hub.challenge handshake)
|  POST — Inbound message payload
|
| The {slug} segment maps to businesses.slug, allowing a single
| Laravel installation to serve unlimited tenants on one domain.
|
*/

Route::prefix('webhook')->group(function (): void {

    // ------------------------------------------------------------------
    // WhatsApp Business API (Meta)
    // ------------------------------------------------------------------

    Route::get(
        uri: 'whatsapp/{slug}',
        action: [WebhookController::class, 'verify'],
    )->defaults('channel', 'whatsapp')->name('webhook.whatsapp.verify');

    Route::post(
        uri: 'whatsapp/{slug}',
        action: [WebhookController::class, 'receive'],
    )->defaults('channel', 'whatsapp')->name('webhook.whatsapp.receive');

    // ------------------------------------------------------------------
    // Messenger Platform (Meta)
    // ------------------------------------------------------------------

    Route::get(
        uri: 'messenger/{slug}',
        action: [WebhookController::class, 'verify'],
    )->defaults('channel', 'messenger')->name('webhook.messenger.verify');

    Route::post(
        uri: 'messenger/{slug}',
        action: [WebhookController::class, 'receive'],
    )->defaults('channel', 'messenger')->name('webhook.messenger.receive');
});

Route::get('health', HealthController::class)->name('api.health');

Route::prefix('internal/voice')
    ->middleware('voice_gateway')
    ->group(function (): void {
        Route::get('tools', [VoiceToolController::class, 'index'])->name('api.internal.voice.tools.index');
        Route::post('tools/{tool}', [VoiceToolController::class, 'execute'])->name('api.internal.voice.tools.execute');

        Route::post('sessions/start', [VoiceSessionController::class, 'start'])->name('api.internal.voice.sessions.start');
        Route::post('sessions/{voiceSession}/events', [VoiceSessionController::class, 'storeEvent'])->name('api.internal.voice.sessions.events.store');
        Route::post('sessions/{voiceSession}/turns', [VoiceSessionController::class, 'storeTurn'])->name('api.internal.voice.sessions.turns.store');
        Route::post('sessions/{voiceSession}/usage', [VoiceSessionController::class, 'storeUsage'])->name('api.internal.voice.sessions.usage.store');
        Route::post('sessions/{voiceSession}/summary', [VoiceSessionController::class, 'storeSummary'])->name('api.internal.voice.sessions.summary.store');
        Route::post('sessions/{voiceSession}/complete', [VoiceSessionController::class, 'complete'])->name('api.internal.voice.sessions.complete');
    });
