<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
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
            'managedByAdmin' => $this->managedByAdmin($request),
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
        $this->ensureManagedByAdmin($request);

        $validated = $request->validate([
            'whatsapp.enabled'           => ['boolean'],
            'whatsapp.provider'          => ['required', 'in:meta_cloud,twilio'],
            'whatsapp.phone_number_id'   => ['nullable', 'string', 'max:50'],
            'whatsapp.access_token'      => ['nullable', 'string', 'max:500'],
            'whatsapp.verify_token'      => ['nullable', 'string', 'max:255'],
            'whatsapp.app_secret'        => ['nullable', 'string', 'max:255'],
            'whatsapp.twilio_account_sid' => ['nullable', 'string', 'max:255'],
            'whatsapp.twilio_auth_token' => ['nullable', 'string', 'max:255'],
            'whatsapp.twilio_from_number' => ['nullable', 'string', 'max:255'],
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
        $this->ensureManagedByAdmin($request);

        $business      = $request->user()->business;
        $channelConfig = $business->channel_config[$channel] ?? [];

        try {
            return match ($channel) {
                'whatsapp' => $this->testWhatsApp($channelConfig),
                'messenger' => $this->testMetaToken((string) ($channelConfig['access_token'] ?? '')),
                default => response()->json(['success' => false, 'message' => 'Unknown channel.']),
            };
        } catch (\Throwable $e) {
            Log::error("ChannelSettingsController: test failed for channel {$channel}.", [
                'business_id' => $business->id,
                'error'       => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => 'Connection error: ' . $e->getMessage()]);
        }
    }

    /**
     * @param  array<string, mixed>  $channelConfig
     */
    private function testWhatsApp(array $channelConfig): JsonResponse
    {
        $provider = (string) ($channelConfig['provider'] ?? 'meta_cloud');

        if ($provider === 'twilio') {
            $accountSid = (string) ($channelConfig['twilio_account_sid'] ?? '');
            $authToken = (string) ($channelConfig['twilio_auth_token'] ?? '');

            if ($accountSid === '' || $authToken === '') {
                return response()->json(['success' => false, 'message' => 'Twilio SID or auth token is missing.']);
            }

            $response = Http::withBasicAuth($accountSid, $authToken)
                ->timeout(10)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}.json");

            if ($response->successful()) {
                return response()->json(['success' => true, 'message' => 'Twilio connection successful.']);
            }

            return response()->json([
                'success' => false,
                'message' => 'Twilio error: ' . ($response->json('message') ?? $response->body()),
            ]);
        }

        return $this->testMetaToken((string) ($channelConfig['access_token'] ?? ''));
    }

    private function testMetaToken(string $accessToken): JsonResponse
    {
        if ($accessToken === '') {
            return response()->json(['success' => false, 'message' => 'No access token configured.']);
        }

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
    }

    private function managedByAdmin(Request $request): bool
    {
        return $request->session()->has('impersonating_as') || $request->user()?->role === 'super_admin';
    }

    private function ensureManagedByAdmin(Request $request): void
    {
        if (!$this->managedByAdmin($request)) {
            throw new AuthorizationException('Channel setup is managed by Kynex Solutions.');
        }
    }
}
