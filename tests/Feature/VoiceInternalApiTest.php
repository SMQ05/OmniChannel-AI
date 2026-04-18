<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
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

    /**
     * @return array{0: Business, 1: VoiceChannel}
     */
    private function seedVoiceBusiness(): array
    {
        $plan = Plan::query()->create([
            'code' => 'voice-test',
            'name' => 'Voice Test',
            'description' => 'Test plan',
            'included_quotas' => ['voice_minutes' => 120],
            'feature_flags' => ['voice_agent' => true],
            'is_active' => true,
        ]);

        $business = Business::query()->create([
            'name' => 'Voice Clinic',
            'business_type' => 'clinic',
            'slug' => 'voice-clinic',
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
            'phone_number' => '+15551234567',
            'config' => ['label' => 'Main Front Desk'],
            'is_enabled' => true,
        ]);

        return [$business, $voiceChannel];
    }
}
