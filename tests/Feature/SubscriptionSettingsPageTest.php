<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\UsageEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_user_can_view_subscription_and_usage_page(): void
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
            'code' => 'starter',
        ], [
            'name' => 'Starter',
            'description' => 'Starter tier',
            'included_quotas' => ['messages_sent' => 100],
            'feature_flags' => ['voice_agent' => false],
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

        UsageEvent::query()->create([
            'business_id' => $business->id,
            'metric' => 'messages_sent',
            'channel' => 'whatsapp',
            'quantity' => 12,
            'status' => 'sent',
            'recorded_at' => now(),
        ]);

        $user = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $response = $this->actingAs($user)->get(route('settings.subscription'));

        $response->assertOk();
        $response->assertSee('Usage & Plan');
        $response->assertSee('Launch');
        $response->assertSee('Quota Usage');
    }
}
