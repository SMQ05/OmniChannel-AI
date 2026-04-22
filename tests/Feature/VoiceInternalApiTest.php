<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Plan;
use App\Models\Provider;
use App\Models\VoiceChannel;
use App\Models\VoiceEvent;
use App\Models\VoiceSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class VoiceInternalApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('voice_gateway.enabled', true);
        Config::set('voice_gateway.internal_api.shared_secret', 'test-shared-secret');
    }

    public function test_voice_internal_routes_require_shared_secret(): void
    {
        $this->getJson('/api/internal/voice/tools')
            ->assertUnauthorized();
    }

    public function test_gateway_can_start_voice_session_for_enabled_business(): void
    {
        [$business, $voiceChannel] = $this->seedVoiceBusiness();

        $response = $this->withHeader('X-Voice-Gateway-Secret', 'test-shared-secret')
            ->postJson('/api/internal/voice/sessions/start', [
                'voice_channel_id' => $voiceChannel->id,
                'provider' => 'telnyx',
                'provider_call_id' => 'call-123',
                'transport_stream_id' => 'stream-123',
                'direction' => 'inbound',
                'from_number' => '+15550001000',
                'to_number' => $voiceChannel->phone_number,
            ]);

        $response->assertOk()
            ->assertJsonPath('accepted', true)
            ->assertJsonPath('voice_session.business_id', $business->id);

        $this->assertDatabaseHas('voice_sessions', [
            'business_id' => $business->id,
            'provider_call_id' => 'call-123',
            'status' => 'initiated',
        ]);

        $this->assertDatabaseHas('call_logs', [
            'business_id' => $business->id,
            'provider_call_id' => 'call-123',
        ]);
    }

    public function test_gateway_rejects_new_voice_session_when_billing_is_suspended(): void
    {
        [$business, $voiceChannel] = $this->seedVoiceBusiness();
        $business->subscription()->update(['lifecycle_status' => 'suspended']);

        $response = $this->withHeader('X-Voice-Gateway-Secret', 'test-shared-secret')
            ->postJson('/api/internal/voice/sessions/start', [
                'voice_channel_id' => $voiceChannel->id,
                'provider' => 'telnyx',
                'provider_call_id' => 'call-456',
                'transport_stream_id' => 'stream-456',
                'direction' => 'inbound',
                'from_number' => '+15550001000',
                'to_number' => $voiceChannel->phone_number,
            ]);

        $response->assertOk()
            ->assertJsonPath('accepted', false)
            ->assertJsonPath('reason', 'Voice is suspended because billing lifecycle is suspended.');

        $this->assertDatabaseHas('voice_sessions', [
            'business_id' => $business->id,
            'provider_call_id' => 'call-456',
            'status' => 'rejected',
        ]);
    }

    public function test_write_tool_calls_are_idempotent(): void
    {
        [$business, $voiceChannel] = $this->seedVoiceBusiness();

        $voiceSession = VoiceSession::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'business_id' => $business->id,
            'voice_channel_id' => $voiceChannel->id,
            'provider' => 'telnyx',
            'status' => 'initiated',
            'from_number' => '+15550001000',
            'to_number' => $voiceChannel->phone_number,
            'initiated_at' => now(),
            'last_activity_at' => now(),
        ]);

        $headers = [
            'X-Voice-Gateway-Secret' => 'test-shared-secret',
            'X-Idempotency-Key' => 'session-1-callback',
        ];

        $payload = [
            'voice_session_id' => $voiceSession->id,
            'payload' => [
                'phone' => '+15550001000',
                'reason' => 'Caller requested a callback',
                'urgency' => 'normal',
            ],
        ];

        $first = $this->withHeaders($headers)
            ->postJson('/api/internal/voice/tools/create_callback_request', $payload);

        $second = $this->withHeaders($headers)
            ->postJson('/api/internal/voice/tools/create_callback_request', $payload);

        $first->assertOk()->assertJsonPath('replayed', false);
        $second->assertOk()->assertJsonPath('replayed', true);

        $this->assertSame(
            1,
            VoiceEvent::query()
                ->where('voice_session_id', $voiceSession->id)
                ->where('event_type', 'callback.requested')
                ->count(),
        );
    }

    public function test_voice_tools_do_not_expose_or_modify_other_tenant_records(): void
    {
        [$business, $voiceChannel] = $this->seedVoiceBusiness();
        [$otherBusiness] = $this->seedVoiceBusiness('other-tenant');

        $localPatient = Patient::query()->create([
            'business_id' => $business->id,
            'name' => 'Local Patient',
            'phone' => '+15550001000',
            'platform_user_id' => '+15550001000',
            'platform' => 'voice',
        ]);

        $foreignPatient = Patient::query()->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Foreign Patient',
            'phone' => '+15559990000',
            'platform_user_id' => '+15559990000',
            'platform' => 'voice',
        ]);

        $foreignProvider = Provider::query()->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Foreign Provider',
            'working_hours' => ['monday' => ['active' => true, 'start' => '09:00', 'end' => '17:00']],
            'slot_duration_minutes' => 30,
            'is_active' => true,
        ]);

        $foreignAppointment = Appointment::query()->create([
            'business_id' => $otherBusiness->id,
            'provider_id' => $foreignProvider->id,
            'patient_id' => $foreignPatient->id,
            'service_type' => 'Foreign Booking',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addMinutes(30),
            'status' => 'confirmed',
            'booked_via' => 'voice',
        ]);

        $voiceSession = VoiceSession::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'business_id' => $business->id,
            'voice_channel_id' => $voiceChannel->id,
            'patient_id' => $localPatient->id,
            'provider' => 'telnyx',
            'status' => 'initiated',
            'from_number' => '+15550001000',
            'to_number' => $voiceChannel->phone_number,
            'initiated_at' => now(),
            'last_activity_at' => now(),
        ]);

        $lookup = $this->withHeader('X-Voice-Gateway-Secret', 'test-shared-secret')
            ->postJson('/api/internal/voice/tools/lookup_patient', [
                'voice_session_id' => $voiceSession->id,
                'payload' => ['patient_id' => $foreignPatient->id],
            ]);

        $lookup->assertOk()
            ->assertJsonPath('result.ok', true)
            ->assertJsonPath('result.found', false);

        $cancel = $this->withHeaders([
            'X-Voice-Gateway-Secret' => 'test-shared-secret',
            'X-Idempotency-Key' => 'tenant-isolation-cancel',
        ])->postJson('/api/internal/voice/tools/cancel_appointment', [
            'voice_session_id' => $voiceSession->id,
            'payload' => ['appointment_id' => $foreignAppointment->id],
        ]);

        $cancel->assertOk()
            ->assertJsonPath('result.ok', false)
            ->assertJsonPath('result.error', 'No appointment could be found to cancel.');

        $book = $this->withHeaders([
            'X-Voice-Gateway-Secret' => 'test-shared-secret',
            'X-Idempotency-Key' => 'tenant-isolation-book',
        ])->postJson('/api/internal/voice/tools/book_appointment', [
            'voice_session_id' => $voiceSession->id,
            'payload' => [
                'patient_id' => $localPatient->id,
                'provider_id' => $foreignProvider->id,
                'date' => now()->addDay()->toDateString(),
                'time' => '10:00',
                'service_type' => 'Consultation',
            ],
        ]);

        $book->assertOk()
            ->assertJsonPath('result.ok', false)
            ->assertJsonPath('result.error', 'Provider or patient could not be resolved.');

        $this->assertSame('confirmed', $foreignAppointment->fresh()->status);
    }

    public function test_start_session_does_not_reuse_patient_from_other_tenant_by_phone(): void
    {
        [$business, $voiceChannel] = $this->seedVoiceBusiness();
        [$otherBusiness] = $this->seedVoiceBusiness('voice-other');

        $foreignPatient = Patient::query()->create([
            'business_id' => $otherBusiness->id,
            'name' => 'Foreign Voice Patient',
            'phone' => '+15550001000',
            'platform_user_id' => '+15550001000',
            'platform' => 'voice',
        ]);

        $response = $this->withHeader('X-Voice-Gateway-Secret', 'test-shared-secret')
            ->postJson('/api/internal/voice/sessions/start', [
                'voice_channel_id' => $voiceChannel->id,
                'provider' => 'telnyx',
                'provider_call_id' => 'call-tenant-phone',
                'transport_stream_id' => 'stream-tenant-phone',
                'direction' => 'inbound',
                'from_number' => '+15550001000',
                'to_number' => $voiceChannel->phone_number,
            ]);

        $response->assertOk()->assertJsonPath('accepted', true);

        $localSession = VoiceSession::query()->where('provider_call_id', 'call-tenant-phone')->firstOrFail();
        $localPatient = Patient::query()->findOrFail((int) $localSession->patient_id);

        $this->assertNotSame($foreignPatient->id, $localPatient->id);
        $this->assertSame($business->id, $localPatient->business_id);
        $this->assertSame($foreignPatient->id, $foreignPatient->fresh()->id);
    }

    /**
     * @return array{0: Business, 1: VoiceChannel}
     */
    private function seedVoiceBusiness(?string $slug = null): array
    {
        $suffix = $slug ?? 'voice-clinic';
        $plan = Plan::query()->create([
            'code' => 'voice-test-' . $suffix,
            'name' => 'Voice Test ' . $suffix,
            'description' => 'Test plan',
            'included_quotas' => ['voice_minutes' => 120],
            'feature_flags' => ['voice_agent' => true],
            'is_active' => true,
        ]);

        $business = Business::query()->create([
            'name' => 'Voice Clinic ' . $suffix,
            'business_type' => 'clinic',
            'slug' => $suffix,
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [
                'voice' => [
                    'enabled' => true,
                    'greeting' => 'Thanks for calling Voice Clinic.',
                ],
            ],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [
                'ai_name' => 'Sara',
                'tone' => 'friendly',
                'language' => 'English',
                'faqs' => [
                    ['q' => 'Do you accept walk-ins?', 'a' => 'We prefer appointments.'],
                ],
            ],
            'is_active' => true,
            'plan' => 'starter',
        ]);

        BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'included_quotas' => ['voice_minutes' => 120],
            'feature_flags' => ['voice_agent' => true],
            'warn_at_ratio' => 0.8,
            'enforce_limits' => false,
            'admin_override' => false,
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        $voiceChannel = VoiceChannel::query()->create([
            'business_id' => $business->id,
            'provider' => 'telnyx',
            'phone_number' => '+1555' . str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT),
            'config' => ['label' => 'Main Front Desk'],
            'is_enabled' => true,
        ]);

        return [$business, $voiceChannel];
    }
}
