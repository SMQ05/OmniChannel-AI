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

    public function test_business_owner_can_update_business_owned_ai_training_fields(): void
    {
        [$business, $user] = $this->makeBusinessUser();

        $response = $this->actingAs($user)->post(route('settings.ai.update'), [
            'ai_name' => 'Sara',
            'business_phone' => '+15550001111',
            'persona' => 'You are calm and efficient.',
            'tone' => 'friendly',
            'language' => 'English',
            'faqs' => [
                ['q' => 'Do you accept walk-ins?', 'a' => 'Walk-ins depend on availability.'],
            ],
            'llm_provider' => 'minimax',
        ]);

        $response->assertRedirect(route('settings.ai'));

        $business->refresh();

        $this->assertSame('Sara', $business->ai_config['ai_name']);
        $this->assertSame('+15550001111', $business->ai_config['business_phone']);
        $this->assertSame('friendly', $business->ai_config['tone']);
        $this->assertSame('claude', $business->ai_config['llm_provider']);
    }

    public function test_impersonated_admin_can_preview_unsaved_ai_prompt_values(): void
    {
        [$business, $user] = $this->makeBusinessUser();

        $response = $this->withSession(['impersonating_as' => 999])->actingAs($user)->post(route('settings.ai.preview'), [
            'ai_name' => 'Sara',
            'persona' => 'You are calm and efficient.',
            'tone' => 'friendly',
            'language' => 'English',
            'business_phone' => '+15550001111',
            'faqs' => [
                ['q' => 'Do you accept walk-ins?', 'a' => 'Walk-ins depend on availability.'],
            ],
        ]);

        $response->assertOk();
        $response->assertSeeText('You are Sara, a professional AI receptionist for Demo Clinic, a clinic.');
        $response->assertSeeText('BUSINESS PHONE: +15550001111');
        $response->assertSeeText('Do you accept walk-ins?');
        $response->assertDontSeeText('Server Error');
    }

    /**
     * @return array{0: Business, 1: User}
     */
    private function makeBusinessUser(): array
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
            'ai_config' => ['llm_provider' => 'claude'],
            'operations_config' => [],
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

        return [$business, $user];
    }
}
