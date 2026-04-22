<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessSubscription;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAiPolicyControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_ai_policy_index(): void
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
            'ai_config' => [
                'ai_name' => 'Sara',
                'persona' => 'You are warm and professional.',
                'tone' => 'friendly',
                'language' => 'English',
                'llm_provider' => 'claude',
            ],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.ai-policy.index'));
        $response->assertStatus(200);
        $response->assertSee('Clinic');
        $response->assertSee('Rate Limits');
    }

    public function test_super_admin_can_view_business_ai_policy(): void
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
            'ai_config' => [
                'ai_name' => 'Sara',
                'persona' => 'You are warm and professional.',
                'tone' => 'friendly',
                'language' => 'English',
                'llm_provider' => 'claude',
            ],
            'is_active' => true,
            'plan' => 'trial',
            'ai_rate_limit_per_hour' => 50,
            'ai_content_safety_enabled' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.ai-policy.show', $business));
        $response->assertStatus(200);
        $response->assertSee('Clinic');
        $response->assertSee('50');
    }

    public function test_super_admin_can_update_ai_policy(): void
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
            'ai_config' => [
                'ai_name' => 'Sara',
                'persona' => 'You are warm and professional.',
                'tone' => 'friendly',
                'language' => 'English',
                'llm_provider' => 'claude',
            ],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.ai-policy.update', $business), [
                'ai_rate_limit_per_hour' => '200',
                'ai_rate_limit_per_day' => '5000',
                'ai_content_safety_enabled' => '1',
                'ai_pii_detection_enabled' => '1',
                'ai_hallucination_guard_enabled' => '0',
                'ai_voice_agent_enabled' => '1',
                'ai_fallback_provider' => 'openrouter',
            ])
            ->assertRedirect(route('admin.ai-policy.show', $business));

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'ai_rate_limit_per_hour' => 200,
            'ai_rate_limit_per_day' => 5000,
            'ai_content_safety_enabled' => true,
            'ai_pii_detection_enabled' => true,
            'ai_hallucination_guard_enabled' => false,
            'ai_voice_agent_enabled' => true,
            'ai_fallback_provider' => 'openrouter',
        ]);

        // Audit log should be created
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'ai_policy.updated',
        ]);
    }

    public function test_super_admin_can_reset_ai_policy_to_defaults(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $plan = Plan::query()->firstOrCreate(
            ['code' => 'starter'],
            [
                'name' => 'Starter',
                'included_quotas' => ['messages_sent' => 100],
                'feature_flags' => [
                    'ai' => [
                        'rate_limit_per_hour' => 100,
                        'rate_limit_per_day' => 1000,
                        'content_safety_enabled' => true,
                        'pii_detection_enabled' => true,
                        'hallucination_guard_enabled' => true,
                        'voice_agent_enabled' => false,
                        'email_agent_enabled' => false,
                        'chat_only_enabled' => false,
                    ],
                ],
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
            'ai_config' => [
                'ai_name' => 'Sara',
                'persona' => 'You are warm and professional.',
                'tone' => 'friendly',
                'language' => 'English',
                'llm_provider' => 'claude',
            ],
            'is_active' => true,
            'plan' => 'trial',
        ]);

        BusinessSubscription::query()->create([
            'business_id' => $business->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'lifecycle_status' => 'active',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.ai-policy.reset-defaults', $business))
            ->assertRedirect(route('admin.ai-policy.show', $business));

        $this->assertDatabaseHas('businesses', [
            'id' => $business->id,
            'ai_rate_limit_per_hour' => 100, // Default
            'ai_rate_limit_per_day' => 1000, // Default
            'ai_content_safety_enabled' => true, // Default
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'ai_policy.reset_to_defaults',
        ]);
    }

    public function test_non_super_admin_cannot_access_ai_policy(): void
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
            'ai_config' => [
                'ai_name' => 'Sara',
            ],
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

        $this->actingAs($owner)->get(route('admin.ai-policy.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.ai-policy.show', $business))->assertForbidden();
        $this->actingAs($owner)->patch(route('admin.ai-policy.update', $business))->assertForbidden();
        $this->actingAs($owner)->post(route('admin.ai-policy.reset-defaults', $business))->assertForbidden();
    }
}
