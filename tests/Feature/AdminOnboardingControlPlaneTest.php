<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessLaunchState;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOnboardingControlPlaneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_onboarding_index(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

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

        BusinessLaunchState::query()->create([
            'business_id' => $business->id,
            'launch_stage' => 'onboarding',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.onboarding.index'));
        $response->assertStatus(200);
        $response->assertSee('Clinic');
    }

    public function test_super_admin_can_view_business_onboarding_details(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

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

        BusinessLaunchState::query()->create([
            'business_id' => $business->id,
            'launch_stage' => 'onboarding',
            'onboarding_started_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.onboarding.show', $business));
        $response->assertStatus(200);
        $response->assertSee('Clinic');
        $response->assertSee('Onboarding');
    }

    public function test_super_admin_can_mark_onboarding_as_complete(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

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

        BusinessLaunchState::query()->create([
            'business_id' => $business->id,
            'launch_stage' => 'onboarding',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.onboarding.complete', $business))
            ->assertRedirect(route('admin.onboarding.show', $business));

        $this->assertDatabaseHas('business_launch_states', [
            'business_id' => $business->id,
            'launch_stage' => 'ready',
        ]);
    }

    public function test_super_admin_can_approve_business_launch(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

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

        $plan = Plan::query()->firstOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'included_quotas' => ['messages_sent' => 100],
                'feature_flags' => ['voice_agent' => false],
                'is_active' => true,
            ],
        );

        BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'lifecycle_status' => 'active',
        ]);

        BusinessLaunchState::query()->create([
            'business_id' => $business->id,
            'launch_stage' => 'onboarding',
            'is_messaging_ready' => false,
            'is_billing_ready' => false,
            'is_voice_ready' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.onboarding.approve', $business))
            ->assertRedirect(route('admin.onboarding.show', $business));

        $this->assertDatabaseHas('business_launch_states', [
            'business_id' => $business->id,
            'launch_stage' => 'ready',
        ]);

        // Audit log should be created
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'launch.approve',
        ]);
    }

    public function test_super_admin_can_reset_onboarding_state(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

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

        BusinessLaunchState::query()->create([
            'business_id' => $business->id,
            'launch_stage' => 'live',
            'onboarding_completed_at' => now(),
            'launch_approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.onboarding.reset', $business), [
                'confirm' => '1',
                'reason' => 'Testing reset functionality',
            ])
            ->assertRedirect(route('admin.onboarding.show', $business));

        $this->assertDatabaseHas('business_launch_states', [
            'business_id' => $business->id,
            'launch_stage' => 'onboarding',
            'onboarding_completed_at' => null,
            'launch_approved_at' => null,
            'live_at' => null,
            'is_messaging_ready' => false,
        ]);
    }

    public function test_super_admin_can_refresh_launch_readiness(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

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

        BusinessLaunchState::query()->create([
            'business_id' => $business->id,
            'launch_stage' => 'onboarding',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.onboarding.refresh-readiness', $business))
            ->assertRedirect(route('admin.onboarding.show', $business));

        // Check that the snapshot was taken (readiness_snapshot_at will be set)
        $this->assertNotNull($business->launchState?->readiness_snapshot_at);
    }

    public function test_non_super_admin_cannot_access_onboarding_controls(): void
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

        $owner = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $this->actingAs($owner)->get(route('admin.onboarding.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.onboarding.show', $business))->assertForbidden();
        $this->actingAs($owner)->post(route('admin.onboarding.complete', $business))->assertForbidden();
        $this->actingAs($owner)->post(route('admin.onboarding.approve', $business))->assertForbidden();
    }
}
