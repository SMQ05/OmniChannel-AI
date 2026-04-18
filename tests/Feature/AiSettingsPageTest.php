<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_impersonated_admin_can_preview_unsaved_ai_prompt_values(): void
    {
        $business = Business::query()->create([
            'name' => 'Demo Clinic',
            'business_type' => 'clinic',
            'slug' => 'demo-clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [],
            'integration_config' => [],
            'reminder_settings' => [],
            'ai_config' => [],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $response = $this->withSession(['impersonating_as' => 999])->actingAs($user)->post(route('settings.ai.preview'), [
            'ai_name' => 'Sara',
            'persona' => 'You are calm and efficient.',
            'tone' => 'friendly',
            'language' => 'English',
            'llm_provider' => 'openrouter',
            'services' => [
                ['name' => 'General Consultation', 'duration_min' => 30, 'price' => 1500],
            ],
            'faqs' => [
                ['q' => 'Do you accept walk-ins?', 'a' => 'Walk-ins depend on availability.'],
            ],
        ]);

        $response->assertOk();
        $response->assertSeeText('You are Sara, a professional AI receptionist for Demo Clinic, a clinic.');
        $response->assertSeeText('General Consultation (30 min)');
        $response->assertSeeText('Do you accept walk-ins?');
        $response->assertDontSeeText('Server Error');
    }
}
