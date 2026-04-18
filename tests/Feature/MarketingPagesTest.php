<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_is_visible_to_guests(): void
    {
        $response = $this->get(route('marketing.home'));

        $response->assertOk();
        $response->assertSee('AI front desk automation');
        $response->assertSee('Kynex Solutions (kynexsolutions.com)');
    }

    public function test_marketing_pages_are_visible_to_guests(): void
    {
        $this->get(route('marketing.features'))
            ->assertOk()
            ->assertSee('Everything you need to run an AI front desk');

        $this->get(route('marketing.pricing'))
            ->assertOk()
            ->assertSee('Pricing built around setup, automation, and scale.')
            ->assertSee('Launch')
            ->assertSee('$69/mo')
            ->assertSee('Growth')
            ->assertSee('$119/mo')
            ->assertSee('Pro')
            ->assertSee('$219/mo');
    }

    public function test_authenticated_users_are_redirected_from_root_to_dashboard(): void
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
            'plan' => 'trial',
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertRedirect(route('dashboard'));
    }
}
