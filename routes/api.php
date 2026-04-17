<?php

declare(strict_types=1);

use App\Http\Controllers\Api\WebhookController;
use App\Http\Controllers\Api\HealthController;
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
