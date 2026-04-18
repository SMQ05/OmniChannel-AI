<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\User;
use App\Models\VoiceChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoiceSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_user_can_view_and_update_voice_settings(): void
    {
        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'pro',
        ]);

        $plan = Plan::query()->firstOrCreate([
            'code' => 'pro',
        ], [
            'name' => 'Pro',
            'included_quotas' => ['voice_minutes' => 200],
            'feature_flags' => ['voice_agent' => true],
            'is_active' => true,
        ]);

        BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'warn_at_ratio' => 0.8,
            'enforce_limits' => false,
            'admin_override' => false,
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

        $this->actingAs($user)
            ->post(route('settings.voice.update'), [
                'voice' => [
                    'enabled' => '1',
                    'transport_provider' => 'telnyx',
                    'stt_provider' => 'deepgram',
                    'llm_provider' => 'openrouter',
                    'tts_provider' => 'elevenlabs',
                    'greeting_message' => 'Hello from voice',
                ],
            ])
            ->assertRedirect(route('settings.voice'));

        $business->refresh();

        $this->assertTrue((bool) $business->channel_config['voice']['enabled']);
        $this->assertSame('telnyx', $business->channel_config['voice']['transport_provider']);

        $this->actingAs($user)
            ->post(route('settings.voice.channels.store'), [
                'provider' => 'telnyx',
                'phone_number' => '+15550100',
                'is_enabled' => '1',
                'config' => ['label' => 'Main line'],
            ])
            ->assertRedirect(route('settings.voice'));

        $this->assertDatabaseHas('voice_channels', [
            'business_id' => $business->id,
            'provider' => 'telnyx',
            'phone_number' => '+15550100',
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($user)->get(route('settings.voice'));

        $response->assertOk();
        $response->assertSee('Voice Agent');
        $response->assertSee('Business Voice Settings');
        $response->assertSee('Configured Voice Channels');
        $response->assertSee('Main line');
        $response->assertDontSee('Provider Test Actions');
    }

    public function test_business_user_can_toggle_existing_voice_channel(): void
    {
        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'pro',
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $channel = VoiceChannel::query()->create([
            'business_id' => $business->id,
            'provider' => 'telnyx',
            'phone_number' => '+15550100',
            'config' => ['label' => 'Main line'],
            'is_enabled' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('settings.voice.channels.toggle', $channel))
            ->assertRedirect(route('settings.voice'));

        $this->assertTrue($channel->fresh()->is_enabled);
    }
}
