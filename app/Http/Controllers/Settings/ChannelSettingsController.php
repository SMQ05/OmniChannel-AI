<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BusinessMessagingChannel;
use App\Services\Audit\AuditLogger;
use App\Services\ChannelReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ChannelSettingsController extends Controller
{
    public function edit(Request $request, ChannelReadinessService $channelReadinessService): View
    {
        $business = $request->user()->business;

        return view('settings.channels', [
            'business' => $business,
            'channels' => $channelReadinessService->forBusiness($business),
            'webhookBase' => url('api/webhook'),
        ]);
    }

    public function update(
        Request $request,
        ChannelReadinessService $channelReadinessService,
        AuditLogger $auditLogger,
    ): RedirectResponse {
        $request->validate([
            'whatsapp.enabled' => ['boolean'],
            'messenger.enabled' => ['boolean'],
            'confirm_disable.whatsapp' => ['nullable', 'boolean'],
            'confirm_disable.messenger' => ['nullable', 'boolean'],
            'disable_reason.whatsapp' => ['nullable', 'string', 'max:255'],
            'disable_reason.messenger' => ['nullable', 'string', 'max:255'],
        ]);

        $business = $request->user()->business;
        $channels = $channelReadinessService->forBusiness($business);

        foreach (['whatsapp' => 'WhatsApp', 'messenger' => 'Messenger'] as $channel => $label) {
            $requestedEnabled = $request->boolean("{$channel}.enabled");
            $current = $channels[$channel];

            if ($requestedEnabled && !$current['connected']) {
                throw ValidationException::withMessages([
                    "{$channel}.enabled" => "{$label} cannot be enabled until the admin-managed connection is complete.",
                ]);
            }

            if ($current['enabled'] && !$requestedEnabled && !$request->boolean("confirm_disable.{$channel}")) {
                throw ValidationException::withMessages([
                    "confirm_disable.{$channel}" => "Confirm the live {$label} disable action before saving.",
                ]);
            }

            /** @var BusinessMessagingChannel $record */
            $record = $channelReadinessService->businessChannel($business, $channel)
                ?? new BusinessMessagingChannel([
                    'business_id' => $business->id,
                    'channel' => $channel,
                ]);

            $wasEnabled = (bool) $record->is_enabled;
            $disableReason = trim((string) $request->input("disable_reason.{$channel}", ''));

            $record->fill([
                'is_enabled' => $requestedEnabled,
                'approved_at' => $requestedEnabled ? ($record->approved_at ?? now()) : $record->approved_at,
                'enabled_at' => $requestedEnabled ? now() : null,
                'disabled_at' => $requestedEnabled ? null : ($current['enabled'] ? now() : $record->disabled_at),
                'disabled_by_user_id' => $requestedEnabled ? null : ($current['enabled'] ? $request->user()->id : $record->disabled_by_user_id),
                'disable_reason' => $requestedEnabled ? null : ($disableReason !== '' ? $disableReason : 'Disabled from business settings.'),
            ]);
            $record->save();

            if (!$wasEnabled && $requestedEnabled) {
                $auditLogger->log(
                    actor: $request->user(),
                    action: 'messaging.channel_enabled',
                    subjectType: BusinessMessagingChannel::class,
                    subjectId: $record->id,
                    payload: ['channel' => $channel, 'provider' => $current['provider']],
                    request: $request,
                    businessId: $business->id,
                );
            }

            if ($current['enabled'] && !$requestedEnabled) {
                $auditLogger->log(
                    actor: $request->user(),
                    action: 'messaging.channel_disabled',
                    subjectType: BusinessMessagingChannel::class,
                    subjectId: $record->id,
                    payload: [
                        'channel' => $channel,
                        'provider' => $current['provider'],
                        'reason' => $record->disable_reason,
                    ],
                    request: $request,
                    businessId: $business->id,
                );
            }
        }

        return redirect()->route('settings.channels')->with('success', 'Messaging channel ownership settings saved.');
    }
}
