<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdminVoiceOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_placeholder_voice_env_values_do_not_show_as_configured(): void
    {
        Config::set('kynex.features.voice_agent', true);
        Config::set('voice.providers.transport.telnyx.api_key', '...');
        Config::set('voice.providers.transport.telnyx.connection_id', '...');
        Config::set('voice.providers.stt.deepgram.api_key', 'YOUR_DEEPGRAM_KEY');
        Config::set('voice.providers.llm.openrouter.api_key', 'null');
        Config::set('voice.providers.tts.elevenlabs.api_key', '');
        Config::set('voice.providers.tts.elevenlabs.voice_id', '...');

        Business::query()->create([
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
            'plan' => 'trial',
        ]);

        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.voice.index'));

        $response->assertOk();
        $response->assertSee('Missing env');
        $response->assertSee('Telnyx');
        $response->assertSee('Deepgram');
        $response->assertSee('OpenRouter');
        $response->assertSee('ElevenLabs');
    }

    public function test_super_admin_can_manage_business_voice_settings_from_admin_voice_console(): void
    {
        Config::set('kynex.features.voice_agent', true);

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
            'channel_config' => [],
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

        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.voice.update', $business), [
                'voice' => [
                    'enabled' => '1',
                    'transport_provider' => 'telnyx',
                    'stt_provider' => 'deepgram',
                    'llm_provider' => 'openrouter',
                    'tts_provider' => 'elevenlabs',
                    'greeting_message' => 'Hello from admin voice',
                ],
            ])
            ->assertRedirect(route('admin.voice.index', ['business' => $business->id]));

        $business->refresh();

        $this->assertTrue((bool) $business->channel_config['voice']['enabled']);
        $this->assertSame('openrouter', $business->channel_config['voice']['llm_provider']);

        $response = $this->actingAs($admin)->get(route('admin.voice.index', ['business' => $business->id]));

        $response->assertOk();
        $response->assertSee('Business Voice Settings');
        $response->assertSee('Provider Test Actions');
        $response->assertSee('Add Voice Channel');
    }
}
