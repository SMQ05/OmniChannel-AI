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

    public function test_business_user_sees_read_only_voice_settings_page(): void
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

        $response = $this->actingAs($user)->get(route('settings.voice'));

        $response->assertOk();
        $response->assertSee('Voice Ownership');
        $response->assertSee('Business-owned voice behavior');
        $response->assertSee('Platform-managed routing');
        $response->assertSee('Read-only');
    }

    public function test_business_user_can_update_business_owned_voice_fields_but_not_platform_routing(): void
    {
        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => ['voice' => ['llm_provider' => 'openrouter']],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'operations_config' => [],
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

        $this->actingAs($user)
            ->post(route('settings.voice.update'), [
                'voice' => [
                    'enabled' => '1',
                    'greeting_message' => 'Hello from voice',
                    'handoff_message' => 'A human will join shortly.',
                    'notes' => 'Business-owned note',
                    'llm_provider' => 'bad-value',
                ],
            ])
            ->assertRedirect(route('settings.voice'));

        $business->refresh();

        $this->assertTrue((bool) $business->channel_config['voice']['enabled']);
        $this->assertSame('Hello from voice', $business->channel_config['voice']['greeting_message']);
        $this->assertSame('openrouter', $business->channel_config['voice']['llm_provider']);

        $this->actingAs($user)
            ->post(route('settings.voice.routing.update'), [
                'voice' => ['llm_provider' => 'openrouter'],
            ])
            ->assertForbidden();
    }

    public function test_impersonated_admin_can_update_and_toggle_voice_settings(): void
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

        $this->withSession(['impersonating_as' => 999])
            ->actingAs($user)
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

        $this->withSession(['impersonating_as' => 999])
            ->actingAs($user)
            ->post(route('settings.voice.channels.store'), [
                'provider' => 'telnyx',
                'phone_number' => '+15550100',
                'is_enabled' => '1',
                'config' => ['label' => 'Main line'],
            ])
            ->assertRedirect(route('settings.voice'));

        $this->actingAs($user)
            ->get(route('settings.voice'))
            ->assertSee('Main line');

        $this->withSession(['impersonating_as' => 999])
            ->actingAs($user)
            ->patch(route('settings.voice.channels.toggle', $channel))
            ->assertRedirect(route('settings.voice'));

        $this->assertTrue($channel->fresh()->is_enabled);
    }
}
