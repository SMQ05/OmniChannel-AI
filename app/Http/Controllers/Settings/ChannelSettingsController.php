<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Manages WhatsApp and Messenger channel configuration (/settings/channels).
 *
 * Persists enabled/credentials state to businesses.channel_config.
 * The test endpoint verifies the access token is valid against the Meta Graph API.
 */
class ChannelSettingsController extends Controller
{
    /**
     * Render the channel settings page.
     *
     * @param  Request  $request
     * @return View
     */
    public function edit(Request $request): View
    {
        $business      = $request->user()->business;
        $channelConfig = $business->channel_config ?? [];

        return view('settings.channels', [
            'business'      => $business,
            'channelConfig' => $channelConfig,
            'webhookBase'   => url('api/webhook'),
        ]);
    }

    /**
     * Save channel configuration for both WhatsApp and Messenger.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'whatsapp.enabled'           => ['boolean'],
            'whatsapp.phone_number_id'   => ['nullable', 'string', 'max:50'],
            'whatsapp.access_token'      => ['nullable', 'string', 'max:500'],
            'whatsapp.verify_token'      => ['nullable', 'string', 'max:255'],
            'whatsapp.app_secret'        => ['nullable', 'string', 'max:255'],
            'messenger.enabled'          => ['boolean'],
            'messenger.page_id'          => ['nullable', 'string', 'max:50'],
            'messenger.access_token'     => ['nullable', 'string', 'max:500'],
            'messenger.verify_token'     => ['nullable', 'string', 'max:255'],
            'messenger.app_secret'       => ['nullable', 'string', 'max:255'],
        ]);

        $business = $request->user()->business;

        $existing = $business->channel_config ?? [];

        $whatsapp = array_merge($existing['whatsapp'] ?? [], $validated['whatsapp'] ?? []);
        $whatsapp['enabled'] = $request->boolean('whatsapp.enabled');

        $messenger = array_merge($existing['messenger'] ?? [], $validated['messenger'] ?? []);
        $messenger['enabled'] = $request->boolean('messenger.enabled');

        $business->channel_config = [
            'whatsapp'  => $whatsapp,
            'messenger' => $messenger,
        ];

        $business->save();

        return redirect()->route('settings.channels')->with('success', 'Channel settings saved.');
    }

    /**
     * Test a channel's access token against the Meta Graph API.
     *
     * Returns JSON: { success: bool, message: string }
     *
     * @param  Request  $request
     * @param  string   $channel  'whatsapp' or 'messenger'
     * @return JsonResponse
     */
    public function test(Request $request, string $channel): JsonResponse
    {
        $business      = $request->user()->business;
        $channelConfig = $business->channel_config[$channel] ?? [];
        $accessToken   = $channelConfig['access_token'] ?? '';

        if ($accessToken === '') {
            return response()->json(['success' => false, 'message' => 'No access token configured.']);
        }

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://graph.facebook.com/v19.0/me');

            if ($response->successful()) {
                return response()->json(['success' => true, 'message' => 'Connection successful.']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Token invalid: ' . ($response->json('error.message') ?? $response->body()),
            ]);
        } catch (\Throwable $e) {
            Log::error("ChannelSettingsController: test failed for channel {$channel}.", [
                'business_id' => $business->id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Connection error: ' . $e->getMessage()]);
        }
    }
}
