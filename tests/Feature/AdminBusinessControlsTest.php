<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBusinessControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_subscription_controls_for_a_business(): void
    {
        $plan = Plan::query()->firstOrCreate(
            ['code' => 'pro'],
            [
                'name' => 'Pro',
                'included_quotas' => ['messages_sent' => 5000],
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
            ->patch(route('admin.businesses.update-subscription', $business), [
                'status' => 'active',
                'warn_at_ratio' => '0.95',
                'enforce_limits' => '1',
                'admin_override' => '1',
                'included_quotas' => json_encode(['messages_sent' => 250], JSON_THROW_ON_ERROR),
                'feature_flags' => json_encode(['voice_agent' => true], JSON_THROW_ON_ERROR),
                'overage_counters' => json_encode(['messages_sent' => 12], JSON_THROW_ON_ERROR),
                'current_period_start' => now()->startOfMonth()->toDateString(),
                'current_period_end' => now()->endOfMonth()->toDateString(),
            ])
            ->assertRedirect(route('admin.businesses.index'));

        $subscription = $business->fresh()->subscription;

        $this->assertNotNull($subscription);
        $this->assertSame('active', $subscription->status);
        $this->assertEquals(0.95, (float) $subscription->warn_at_ratio);
        $this->assertTrue($subscription->enforce_limits);
        $this->assertTrue($subscription->admin_override);
        $this->assertSame(250, $subscription->included_quotas['messages_sent']);
        $this->assertTrue($subscription->feature_flags['voice_agent']);
        $this->assertSame(12, $subscription->overage_counters['messages_sent']);
    }
}
