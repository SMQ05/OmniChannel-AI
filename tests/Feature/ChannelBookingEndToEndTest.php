<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\AppointmentAgent;
use App\Mail\AppointmentLifecycleMail;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\BusinessMessagingChannel;
use App\Models\BusinessService;
use App\Models\BusinessSubscription;
use App\Models\MessagingChannelConnection;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\VoiceChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ChannelBookingEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_booking_flow_completes_create_retry_reschedule_and_cancel_with_sync_and_email(): void
    {
        Mail::fake();

        [$business, $provider, $service, $patient] = $this->makeMessagingBusiness('whatsapp', '15551234567');
        $this->bindAgentForFlow($provider->id, $service->id);
        $this->fakeExternalTraffic('whatsapp', '123456');

        $bookPayload = $this->whatsAppPayload('wamid.book.1', 'book', '15551234567', '1710000001');
        $bookRaw = json_encode($bookPayload, JSON_THROW_ON_ERROR);
        $bookSignature = 'sha256=' . hash_hmac('sha256', $bookRaw, 'meta-app-secret');

        $this->withHeader('X-Hub-Signature-256', $bookSignature)
            ->postJson(route('webhook.whatsapp.receive', ['slug' => $business->slug]), $bookPayload)
            ->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseCount('appointments', 1);
        $appointment = Appointment::query()->firstOrFail();
        $this->assertSame('confirmed', $appointment->status);
        $this->assertTrue((bool) $appointment->synced_to_calendar);
        $this->assertTrue((bool) $appointment->synced_to_sheets);
        $this->assertDatabaseHas('booking_actions', [
            'business_id' => $business->id,
            'action' => 'create',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'appointment.booked',
        ]);
        $this->assertDatabaseHas('outbound_message_attempts', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
            'message_text' => 'Your appointment is confirmed for April 25, 2026 at 10:00 AM with Dr Flow.',
        ]);
        Mail::assertSent(AppointmentLifecycleMail::class, function (AppointmentLifecycleMail $mail) use ($patient): bool {
            return $mail->hasTo($patient->email) && $mail->action === 'created';
        });

        $this->withHeader('X-Hub-Signature-256', $bookSignature)
            ->postJson(route('webhook.whatsapp.receive', ['slug' => $business->slug]), $bookPayload)
            ->assertOk()
            ->assertJson(['status' => 'duplicate']);

        $this->assertDatabaseCount('appointments', 1);

        $reschedulePayload = $this->whatsAppPayload('wamid.book.2', 'reschedule', '15551234567', '1710000002');
        $rescheduleRaw = json_encode($reschedulePayload, JSON_THROW_ON_ERROR);
        $rescheduleSignature = 'sha256=' . hash_hmac('sha256', $rescheduleRaw, 'meta-app-secret');

        $this->withHeader('X-Hub-Signature-256', $rescheduleSignature)
            ->postJson(route('webhook.whatsapp.receive', ['slug' => $business->slug]), $reschedulePayload)
            ->assertOk();

        $this->assertDatabaseCount('appointments', 2);
        $replacement = Appointment::query()->where('status', 'confirmed')->latest('id')->firstOrFail();
        $cancelled = Appointment::query()->where('status', 'cancelled')->oldest('id')->firstOrFail();
        $this->assertTrue((bool) $replacement->synced_to_calendar);
        $this->assertTrue((bool) $replacement->synced_to_sheets);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'appointment.rescheduled',
        ]);
        $this->assertDatabaseHas('outbound_message_attempts', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
            'message_text' => 'Your appointment has been rescheduled to April 26, 2026 at 11:30 AM with Dr Flow.',
        ]);
        Mail::assertSent(AppointmentLifecycleMail::class, function (AppointmentLifecycleMail $mail) use ($patient): bool {
            return $mail->hasTo($patient->email) && $mail->action === 'rescheduled';
        });

        $cancelPayload = $this->whatsAppPayload('wamid.book.3', 'cancel', '15551234567', '1710000003');
        $cancelRaw = json_encode($cancelPayload, JSON_THROW_ON_ERROR);
        $cancelSignature = 'sha256=' . hash_hmac('sha256', $cancelRaw, 'meta-app-secret');

        $this->withHeader('X-Hub-Signature-256', $cancelSignature)
            ->postJson(route('webhook.whatsapp.receive', ['slug' => $business->slug]), $cancelPayload)
            ->assertOk();

        $this->assertSame('cancelled', $replacement->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'appointment.cancelled',
        ]);
        $this->assertDatabaseHas('outbound_message_attempts', [
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'status' => 'sent',
            'message_text' => 'Your appointment for April 26, 2026 at 11:30 AM has been cancelled.',
        ]);
        Mail::assertSent(AppointmentLifecycleMail::class, function (AppointmentLifecycleMail $mail) use ($patient): bool {
            return $mail->hasTo($patient->email) && $mail->action === 'cancelled';
        });

        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/calendar/v3/calendars/primary/events'));
        Http::assertSent(fn ($request) => $request->method() === 'DELETE' && str_contains($request->url(), '/calendar/v3/calendars/primary/events/'));
        Http::assertSent(fn ($request) => $request->method() === 'POST' && str_contains($request->url(), '/values/Appointments!A1:append'));
        Http::assertSent(fn ($request) => $request->method() === 'PUT' && str_contains($request->url(), '/values/'));
    }

    public function test_messenger_booking_flow_completes_create_reschedule_and_cancel_with_sync_and_email(): void
    {
        Mail::fake();

        [$business, $provider, $service, $patient] = $this->makeMessagingBusiness('messenger', 'psid-123');
        $this->bindAgentForFlow($provider->id, $service->id);
        $this->fakeExternalTraffic('messenger', 'page-123');

        foreach ([
            ['mid.1', 'book', '1711000001'],
            ['mid.2', 'reschedule', '1711000002'],
            ['mid.3', 'cancel', '1711000003'],
        ] as [$mid, $text, $timestamp]) {
            $payload = $this->messengerPayload($mid, $text, 'psid-123', $timestamp);
            $raw = json_encode($payload, JSON_THROW_ON_ERROR);
            $signature = 'sha256=' . hash_hmac('sha256', $raw, 'meta-app-secret');

            $this->withHeader('X-Hub-Signature-256', $signature)
                ->postJson(route('webhook.messenger.receive', ['slug' => $business->slug]), $payload)
                ->assertOk();
        }

        $this->assertDatabaseCount('appointments', 2);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'appointment.booked',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'appointment.rescheduled',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'appointment.cancelled',
        ]);
        $this->assertDatabaseHas('outbound_message_attempts', [
            'business_id' => $business->id,
            'channel' => 'messenger',
            'status' => 'sent',
            'message_text' => 'Your appointment is confirmed for April 25, 2026 at 10:00 AM with Dr Flow.',
        ]);
        $this->assertDatabaseHas('outbound_message_attempts', [
            'business_id' => $business->id,
            'channel' => 'messenger',
            'status' => 'sent',
            'message_text' => 'Your appointment has been rescheduled to April 26, 2026 at 11:30 AM with Dr Flow.',
        ]);
        $this->assertDatabaseHas('outbound_message_attempts', [
            'business_id' => $business->id,
            'channel' => 'messenger',
            'status' => 'sent',
            'message_text' => 'Your appointment for April 26, 2026 at 11:30 AM has been cancelled.',
        ]);
        Mail::assertSent(AppointmentLifecycleMail::class, 3);
    }

    public function test_voice_booking_flow_completes_create_retry_reschedule_cancel_and_handoff_path(): void
    {
        Mail::fake();
        Config::set('voice_gateway.enabled', true);
        Config::set('voice_gateway.internal_api.shared_secret', 'test-shared-secret');

        [$business, $provider, $service, $patient, $voiceChannel] = $this->makeVoiceBusiness();
        $this->fakeExternalTraffic('voice', 'voice');

        $startResponse = $this->withHeader('X-Voice-Gateway-Secret', 'test-shared-secret')
            ->postJson('/api/internal/voice/sessions/start', [
                'voice_channel_id' => $voiceChannel->id,
                'provider' => 'telnyx',
                'provider_call_id' => 'call-flow-1',
                'transport_stream_id' => 'stream-flow-1',
                'direction' => 'inbound',
                'from_number' => '+15550001111',
                'to_number' => $voiceChannel->phone_number,
            ])->assertOk();

        $voiceSessionId = (int) $startResponse->json('voice_session.id');

        $bookResponse = $this->withHeaders([
            'X-Voice-Gateway-Secret' => 'test-shared-secret',
            'X-Idempotency-Key' => 'voice-book-1',
        ])->postJson('/api/internal/voice/tools/book_appointment', [
            'voice_session_id' => $voiceSessionId,
            'payload' => [
                'patient_id' => $patient->id,
                'provider_id' => $provider->id,
                'service_id' => $service->id,
                'date' => '2026-04-25',
                'time' => '10:00',
            ],
        ]);

        $bookResponse->assertOk()
            ->assertJsonPath('replayed', false)
            ->assertJsonPath('result.ok', true)
            ->assertJsonPath('result.confirmation', 'Your appointment is confirmed for April 25, 2026 at 10:00 AM with Dr Flow.');

        $this->withHeaders([
            'X-Voice-Gateway-Secret' => 'test-shared-secret',
            'X-Idempotency-Key' => 'voice-book-1',
        ])->postJson('/api/internal/voice/tools/book_appointment', [
            'voice_session_id' => $voiceSessionId,
            'payload' => [
                'patient_id' => $patient->id,
                'provider_id' => $provider->id,
                'service_id' => $service->id,
                'date' => '2026-04-25',
                'time' => '10:00',
            ],
        ])->assertOk()->assertJsonPath('replayed', true);

        $appointment = Appointment::query()->where('booked_via', 'voice')->firstOrFail();
        $this->assertTrue((bool) $appointment->synced_to_calendar);
        $this->assertTrue((bool) $appointment->synced_to_sheets);

        $this->withHeaders([
            'X-Voice-Gateway-Secret' => 'test-shared-secret',
            'X-Idempotency-Key' => 'voice-reschedule-1',
        ])->postJson('/api/internal/voice/tools/reschedule_appointment', [
            'voice_session_id' => $voiceSessionId,
            'payload' => [
                'appointment_id' => $appointment->id,
                'provider_id' => $provider->id,
                'service_id' => $service->id,
                'date' => '2026-04-26',
                'time' => '11:30',
            ],
        ])->assertOk()
            ->assertJsonPath('result.ok', true)
            ->assertJsonPath('result.confirmation', 'Your appointment has been rescheduled to April 26, 2026 at 11:30 AM with Dr Flow.');

        $replacement = Appointment::query()->where('status', 'confirmed')->latest('id')->firstOrFail();

        $this->withHeaders([
            'X-Voice-Gateway-Secret' => 'test-shared-secret',
            'X-Idempotency-Key' => 'voice-cancel-1',
        ])->postJson('/api/internal/voice/tools/cancel_appointment', [
            'voice_session_id' => $voiceSessionId,
            'payload' => [
                'appointment_id' => $replacement->id,
            ],
        ])->assertOk()
            ->assertJsonPath('result.ok', true)
            ->assertJsonPath('result.confirmation', 'Your appointment for April 26, 2026 at 11:30 AM has been cancelled.');

        $this->withHeaders([
            'X-Voice-Gateway-Secret' => 'test-shared-secret',
            'X-Idempotency-Key' => 'voice-handoff-1',
        ])->postJson('/api/internal/voice/tools/notify_staff', [
            'voice_session_id' => $voiceSessionId,
            'payload' => [
                'message' => 'Caller requested human handoff.',
                'urgency' => 'high',
            ],
        ])->assertOk()
            ->assertJsonPath('result.ok', true);

        $this->assertDatabaseHas('voice_events', [
            'voice_session_id' => $voiceSessionId,
            'event_type' => 'staff.notified',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'appointment.booked',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'appointment.rescheduled',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'appointment.cancelled',
        ]);
        Mail::assertSent(AppointmentLifecycleMail::class, 3);
    }

    private function bindAgentForFlow(int $providerId, int $serviceId): void
    {
        $this->app->instance(AppointmentAgent::class, new class($providerId, $serviceId) extends AppointmentAgent {
            public function __construct(private readonly int $providerId, private readonly int $serviceId)
            {
                parent::__construct();
            }

            public function handle(\App\Models\Business $business, \App\Models\ConversationLog $conversationLog, array $availableSlots, string $inboundText): array
            {
                return match ($inboundText) {
                    'book' => [
                        'intent' => 'book',
                        'provider_id' => $this->providerId,
                        'service_id' => $this->serviceId,
                        'date' => '2026-04-25',
                        'time' => '10:00',
                        'service_type' => 'Flow Consult',
                        'reply_text' => 'placeholder',
                        'needs_human' => false,
                    ],
                    'reschedule' => [
                        'intent' => 'reschedule',
                        'provider_id' => $this->providerId,
                        'service_id' => $this->serviceId,
                        'date' => '2026-04-26',
                        'time' => '11:30',
                        'service_type' => 'Flow Consult',
                        'reply_text' => 'placeholder',
                        'needs_human' => false,
                    ],
                    'cancel' => [
                        'intent' => 'cancel',
                        'provider_id' => $this->providerId,
                        'service_id' => $this->serviceId,
                        'date' => null,
                        'time' => null,
                        'service_type' => 'Flow Consult',
                        'reply_text' => 'placeholder',
                        'needs_human' => false,
                    ],
                    default => [
                        'intent' => 'faq',
                        'provider_id' => null,
                        'service_id' => null,
                        'date' => null,
                        'time' => null,
                        'service_type' => null,
                        'reply_text' => 'placeholder',
                        'needs_human' => false,
                    ],
                };
            }
        });
    }

    /**
     * @return array{0: Business, 1: Provider, 2: BusinessService, 3: Patient}
     */
    private function makeMessagingBusiness(string $channel, string $platformUserId): array
    {
        $business = Business::query()->create([
            'name' => ucfirst($channel) . ' Flow Clinic',
            'business_type' => 'clinic',
            'slug' => $channel . '-flow-clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [
                'whatsapp' => [
                    'enabled' => $channel === 'whatsapp',
                    'provider' => 'meta_cloud',
                    'phone_number_id' => '123456',
                ],
                'messenger' => [
                    'enabled' => $channel === 'messenger',
                    'provider' => 'meta',
                    'page_id' => 'page-123',
                ],
            ],
            'integration_config' => [
                'google_credentials' => ['client_id' => 'google-client-id'],
                'google_calendar' => ['enabled' => true, 'calendar_id' => 'primary'],
                'google_sheets' => ['enabled' => true, 'spreadsheet_id' => 'sheet-123', 'sheet_name' => 'Appointments'],
            ],
            'integration_secrets' => [
                'google_credentials' => ['client_secret' => 'google-secret'],
                'google_calendar' => ['token' => ['access_token' => 'calendar-token', 'refresh_token' => 'calendar-refresh', 'expires_at' => time() + 3600]],
                'google_sheets' => ['token' => ['access_token' => 'sheets-token', 'refresh_token' => 'sheets-refresh', 'expires_at' => time() + 3600]],
            ],
            'reminder_settings' => [
                'reminders' => [
                    ['offset_hours' => 24, 'message_template' => 'Reminder for {patient_name} at {time}'],
                ],
            ],
            'ai_config' => ['llm_provider' => 'claude'],
            'operations_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        BusinessMessagingChannel::query()->create([
            'business_id' => $business->id,
            'channel' => $channel,
            'is_enabled' => true,
            'approved_at' => now(),
            'enabled_at' => now(),
        ]);

        MessagingChannelConnection::query()->create([
            'business_id' => $business->id,
            'channel' => $channel,
            'provider' => $channel === 'whatsapp' ? 'meta_cloud' : 'meta',
            'status' => 'connected',
            'credentials' => [
                'access_token' => 'token',
                'verify_token' => 'verify-token',
                'app_secret' => 'meta-app-secret',
            ],
            'runtime_config' => $channel === 'whatsapp'
                ? ['phone_number_id' => '123456']
                : ['page_id' => 'page-123'],
            'connected_at' => now(),
        ]);

        $provider = Provider::query()->create([
            'business_id' => $business->id,
            'name' => 'Dr Flow',
            'working_hours' => ['friday' => ['active' => true, 'start' => '09:00', 'end' => '17:00'], 'saturday' => ['active' => true, 'start' => '09:00', 'end' => '17:00']],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $service = BusinessService::query()->create([
            'business_id' => $business->id,
            'name' => 'Flow Consult',
            'slug' => 'flow-consult',
            'duration_minutes' => 30,
            'is_active' => true,
            'booking_rules' => [
                'buffer_before_minutes' => 0,
                'buffer_after_minutes' => 0,
                'allow_online_booking' => true,
                'requires_manual_confirmation' => false,
            ],
        ]);
        $service->providers()->sync([$provider->id]);

        $patient = Patient::query()->create([
            'business_id' => $business->id,
            'name' => 'Patient Flow',
            'phone' => $platformUserId,
            'email' => $channel . '.patient@example.com',
            'platform_user_id' => $platformUserId,
            'platform' => $channel,
        ]);

        return [$business, $provider, $service, $patient];
    }

    /**
     * @return array{0: Business, 1: Provider, 2: BusinessService, 3: Patient, 4: VoiceChannel}
     */
    private function makeVoiceBusiness(): array
    {
        $plan = Plan::query()->create([
            'code' => 'voice-pro',
            'name' => 'Voice Pro',
            'included_quotas' => ['voice_minutes' => 500],
            'feature_flags' => ['voice_agent' => true],
            'is_active' => true,
        ]);

        $business = Business::query()->create([
            'name' => 'Voice Flow Clinic',
            'business_type' => 'clinic',
            'slug' => 'voice-flow-clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [
                'voice' => [
                    'enabled' => true,
                    'llm_provider' => 'openrouter',
                ],
            ],
            'integration_config' => [
                'google_credentials' => ['client_id' => 'google-client-id'],
                'google_calendar' => ['enabled' => true, 'calendar_id' => 'primary'],
                'google_sheets' => ['enabled' => true, 'spreadsheet_id' => 'sheet-123', 'sheet_name' => 'Appointments'],
            ],
            'integration_secrets' => [
                'google_credentials' => ['client_secret' => 'google-secret'],
                'google_calendar' => ['token' => ['access_token' => 'calendar-token', 'refresh_token' => 'calendar-refresh', 'expires_at' => time() + 3600]],
                'google_sheets' => ['token' => ['access_token' => 'sheets-token', 'refresh_token' => 'sheets-refresh', 'expires_at' => time() + 3600]],
            ],
            'reminder_settings' => [
                'reminders' => [
                    ['offset_hours' => 24, 'message_template' => 'Reminder for {patient_name} at {time}'],
                ],
            ],
            'ai_config' => [],
            'operations_config' => [],
            'is_active' => true,
            'plan' => 'pro',
        ]);

        BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'lifecycle_status' => 'active',
            'warn_at_ratio' => 0.8,
            'enforce_limits' => false,
            'admin_override' => false,
            'feature_flags' => ['voice_agent' => true],
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        $provider = Provider::query()->create([
            'business_id' => $business->id,
            'name' => 'Dr Flow',
            'working_hours' => ['friday' => ['active' => true, 'start' => '09:00', 'end' => '17:00'], 'saturday' => ['active' => true, 'start' => '09:00', 'end' => '17:00']],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $service = BusinessService::query()->create([
            'business_id' => $business->id,
            'name' => 'Flow Consult',
            'slug' => 'flow-consult',
            'duration_minutes' => 30,
            'is_active' => true,
        ]);
        $service->providers()->sync([$provider->id]);

        $patient = Patient::query()->create([
            'business_id' => $business->id,
            'name' => 'Voice Patient',
            'phone' => '+15550001111',
            'email' => 'voice.patient@example.com',
            'platform_user_id' => '+15550001111',
            'platform' => 'voice',
        ]);

        $voiceChannel = VoiceChannel::query()->create([
            'business_id' => $business->id,
            'label' => 'Main Line',
            'provider' => 'telnyx',
            'phone_number' => '+15550002222',
            'is_enabled' => true,
        ]);

        return [$business, $provider, $service, $patient, $voiceChannel];
    }

    private function fakeExternalTraffic(string $channel, string $channelTarget): void
    {
        $sheetLookupCalls = 0;
        $calendarCreates = 0;

        Http::fake(function (\Illuminate\Http\Client\Request $request) use ($channel, $channelTarget, &$sheetLookupCalls, &$calendarCreates) {
            $url = $request->url();

            if (str_contains($url, 'graph.facebook.com') && $channel === 'whatsapp' && str_contains($url, '/' . $channelTarget . '/messages')) {
                return Http::response(['messages' => [['id' => 'wamid.reply.' . uniqid()]]], 200);
            }

            if (str_contains($url, 'graph.facebook.com') && $channel === 'messenger' && str_contains($url, '/me/messages')) {
                return Http::response(['message_id' => 'mid.reply.' . uniqid()], 200);
            }

            if (str_contains($url, '/calendar/v3/calendars/primary/events') && $request->method() === 'POST') {
                $calendarCreates++;

                return Http::response(['id' => 'ge-' . $calendarCreates], 200);
            }

            if (str_contains($url, '/calendar/v3/calendars/primary/events/') && $request->method() === 'PATCH') {
                return Http::response(['id' => 'ge-updated'], 200);
            }

            if (str_contains($url, '/calendar/v3/calendars/primary/events/') && $request->method() === 'DELETE') {
                return Http::response('', 204);
            }

            if (str_contains($url, '/values/Appointments!A1:append')) {
                return Http::response(['updates' => ['updatedRange' => 'Appointments!A1:K1']], 200);
            }

            if (str_contains($url, '/values/Appointments%21A%3AA') || str_contains($url, '/values/Appointments!A:A')) {
                $sheetLookupCalls++;

                return Http::response([
                    'values' => $sheetLookupCalls === 1
                        ? [['1']]
                        : [['1'], ['2']],
                ], 200);
            }

            if (str_contains($url, '/values/Appointments!A') && $request->method() === 'PUT') {
                return Http::response(['updatedCells' => 11], 200);
            }

            return Http::response([], 200);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function whatsAppPayload(string $messageId, string $text, string $senderId, string $timestamp): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'business-entry',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['phone_number_id' => '123456'],
                        'contacts' => [[
                            'profile' => ['name' => 'Patient Flow'],
                            'wa_id' => $senderId,
                        ]],
                        'messages' => [[
                            'id' => $messageId,
                            'from' => $senderId,
                            'timestamp' => $timestamp,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messengerPayload(string $messageId, string $text, string $senderId, string $timestamp): array
    {
        return [
            'object' => 'page',
            'entry' => [[
                'id' => 'page-123',
                'time' => (int) $timestamp,
                'messaging' => [[
                    'sender' => ['id' => $senderId],
                    'recipient' => ['id' => 'page-123'],
                    'timestamp' => (int) $timestamp,
                    'message' => [
                        'mid' => $messageId,
                        'text' => $text,
                    ],
                ]],
            ]],
        ];
    }
}
