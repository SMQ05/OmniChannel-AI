<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiagnosticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_business_user_can_view_diagnostics_page(): void
    {
        $business = Business::query()->create([
            'name' => 'Clinic',
            'business_type' => 'clinic',
            'slug' => 'clinic',
            'timezone' => 'UTC',
            'locale' => 'en',
            'channel_config' => [
                'whatsapp' => ['enabled' => false],
                'messenger' => ['enabled' => false],
            ],
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

        $response = $this->actingAs($user)->get(route('settings.diagnostics'));

        $response->assertOk();
        $response->assertSee('Diagnostics');
        $response->assertSee('Webhook Endpoints');
    }
}
