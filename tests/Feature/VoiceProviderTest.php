<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\AppointmentAgent;
use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VoiceProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_impersonated_admin_can_run_voice_provider_tests(): void
    {
        Config::set('kynex.features.voice_agent', true);
        Config::set('voice.providers.transport.telnyx.api_key', 'telnyx-key');
        Config::set('voice.providers.transport.telnyx.connection_id', 'conn-123');
        Config::set('voice.providers.stt.deepgram.api_key', 'deepgram-key');
        Config::set('voice.providers.stt.deepgram.model', 'nova-3');
        Config::set('voice.providers.llm.openrouter.api_key', 'openrouter-key');
        Config::set('voice.providers.llm.openrouter.model', 'openai/gpt-4');
        Config::set('voice.providers.tts.elevenlabs.api_key', 'elevenlabs-key');
        Config::set('voice.providers.tts.elevenlabs.voice_id', 'voice-123');

        Http::fake([
            'https://api.telnyx.com/*' => Http::response(['data' => ['call_control_id' => 'call-1']], 200),
            'https://api.deepgram.com/*' => Http::response([
                'results' => ['channels' => [['alternatives' => [['transcript' => 'hello from deepgram']]]]],
            ], 200),
            'https://api.elevenlabs.io/*' => Http::response('fake-mp3', 200),
        ]);

        $plan = Plan::query()->firstOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Pro',
                'included_quotas' => ['voice_minutes' => 200],
                'feature_flags' => ['voice_agent' => true],
                'is_active' => true,
            ],
        );

        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [
                'voice' => [
                    'enabled' => true,
                    'transport_provider' => 'telnyx',
                    'stt_provider' => 'deepgram',
                    'llm_provider' => 'openrouter',
                    'tts_provider' => 'elevenlabs',
                ],
            ],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'pro',
        ]);

        BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'warn_at_ratio' => 0.8,
            'enforce_limits' => false,
            'admin_override' => false,
            'feature_flags' => ['voice_agent' => true],
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $this->app->instance(AppointmentAgent::class, new class extends AppointmentAgent {
            public function respond(\App\Models\Business $business, array $messages, array $availableSlots = [], ?string $providerOverride = null): array
            {
                return [
                    'intent' => 'faq',
                    'provider_id' => null,
                    'date' => null,
                    'time' => null,
                    'service_type' => null,
                    'reply_text' => 'Shared booking brain response.',
                    'needs_human' => false,
                ];
            }
        });

        $this->withSession(['impersonating_as' => 999])->actingAs($user)
            ->post(route('settings.voice.test', 'transport'), [
                'to' => '+15550001111',
                'from' => '+15550002222',
            ])
            ->assertRedirect(route('settings.voice'));

        $this->withSession(['impersonating_as' => 999])->actingAs($user)
            ->post(route('settings.voice.test', 'stt'), [
                'audio_reference' => 'https://example.com/audio.wav',
            ])
            ->assertRedirect(route('settings.voice'));

        $this->withSession(['impersonating_as' => 999])->actingAs($user)
            ->post(route('settings.voice.test', 'llm'), [
                'prompt' => 'Book me for tomorrow morning.',
            ])
            ->assertRedirect(route('settings.voice'));

        $this->withSession(['impersonating_as' => 999])->actingAs($user)
            ->post(route('settings.voice.test', 'tts'), [
                'text' => 'Your booking is confirmed.',
            ])
            ->assertRedirect(route('settings.voice'));

        $this->assertDatabaseCount('usage_events', 4);
    }
}
