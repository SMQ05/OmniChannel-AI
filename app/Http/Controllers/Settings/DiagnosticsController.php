<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessage;
use App\Models\Appointment;
use App\Models\InboundWebhook;
use App\Services\Diagnostics\DiagnosticsService;
use App\Services\Google\GoogleCalendarService;
use App\Services\Google\GoogleSheetsService;
use App\Services\Messaging\OutboundMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class DiagnosticsController extends Controller
{
    public function index(Request $request, DiagnosticsService $diagnosticsService): View
    {
        $business = $request->user()->business;

        return view('settings.diagnostics', [
            'business' => $business,
            'diagnostics' => $diagnosticsService->forBusiness($business),
        ]);
    }

    public function sendChannelTest(
        Request $request,
        string $channel,
        OutboundMessageService $outboundMessageService,
    ): RedirectResponse {
        $validated = $request->validate([
            'recipient' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $business = $request->user()->business;

        $outboundMessageService->deliver(
            business: $business,
            channel: $channel,
            recipientPlatformId: $validated['recipient'],
            message: $validated['message'] ?: 'Kynex diagnostics test message.',
            idempotencyKey: sprintf('diagnostics:%s:%s', $channel, md5($validated['recipient'] . ($validated['message'] ?? ''))),
            correlationId: (string) \Illuminate\Support\Str::uuid(),
            meta: ['type' => 'diagnostics_test'],
        );

        return back()->with('success', ucfirst($channel) . ' test message queued successfully.');
    }

    public function testGoogle(
        Request $request,
        string $service,
        GoogleCalendarService $calendarService,
        GoogleSheetsService $sheetsService,
    ): RedirectResponse {
        $business = $request->user()->business;

        $fakeAppointment = new Appointment([
            'business_id' => $business->id,
            'id' => 0,
            'service_type' => 'Diagnostics Test',
            'start_time' => now()->addDays(30)->utc(),
            'end_time' => now()->addDays(30)->addMinutes(30)->utc(),
            'status' => 'confirmed',
            'booked_via' => 'manual',
        ]);

        $fakeAppointment->setRelation('patient', new \App\Models\Patient(['name' => 'Diagnostics Test', 'phone' => '']));
        $fakeAppointment->setRelation('provider', new \App\Models\Provider(['name' => 'Diagnostics Test']));

        if ($service === 'google_calendar') {
            $eventId = $calendarService->createEvent($business, $fakeAppointment);
            $calendarService->deleteEvent($business, $eventId);
        } else {
            $sheetsService->appendRow($business, $fakeAppointment);
        }

        return back()->with('success', ucfirst(str_replace('_', ' ', $service)) . ' test completed.');
    }

    public function retryFailedJobs(): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return back()->with('success', 'Failed jobs retry requested.');
    }

    public function replayLastInboundWebhook(Request $request): RedirectResponse
    {
        $business = $request->user()->business;

        $webhook = InboundWebhook::query()
            ->where('business_id', $business->id)
            ->latest('created_at')
            ->first();

        if ($webhook === null) {
            return back()->withErrors(['diagnostics' => 'No inbound webhook found to replay.']);
        }

        $webhook->forceFill([
            'last_replayed_at' => now(),
            'status' => 'received',
        ])->save();

        ProcessIncomingMessage::dispatch($webhook->id);

        return back()->with('success', 'Last inbound webhook replayed.');
    }
}
