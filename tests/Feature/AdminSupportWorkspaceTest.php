<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\BusinessLaunchState;
use App\Models\InboundWebhook;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSupportWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_support_index(): void
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

        $response = $this->actingAs($admin)->get(route('admin.support.index'));
        $response->assertStatus(200);
        $response->assertSee('Clinic');
    }

    public function test_super_admin_can_view_business_support_workspace(): void
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

        $response = $this->actingAs($admin)->get(route('admin.support.show', $business));
        $response->assertStatus(200);
        $response->assertSee('Clinic');
        $response->assertSee('Add Support Note');
    }

    public function test_super_admin_can_add_support_note(): void
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

        $this->actingAs($admin)
            ->post(route('admin.support.add-note', $business), [
                'note' => 'Customer requested priority support for billing issue.',
                'is_private' => '1',
            ])
            ->assertRedirect(route('admin.support.show', $business));

        // Note is stored in audit log for support actions
        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'support.note_private_added',
            'payload->note_preview' => 'Customer requested priority support for billing issue.',
        ]);
    }

    public function test_super_admin_can_add_public_support_note(): void
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

        $this->actingAs($admin)
            ->post(route('admin.support.add-note', $business), [
                'note' => 'Public update: System maintenance scheduled.',
                'is_private' => '0',
            ])
            ->assertRedirect(route('admin.support.show', $business));

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'support.note_public_added',
            'payload->note_preview' => 'Public update: System maintenance scheduled.',
        ]);
    }

    public function test_super_admin_can_replay_last_inbound_message(): void
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

        // Create an inbound webhook first
        InboundWebhook::query()->create([
            'business_id' => $business->id,
            'channel' => 'whatsapp',
            'business_slug' => $business->slug,
            'correlation_id' => \Illuminate\Support\Str::uuid()->toString(),
            'idempotency_key' => 'phase7-test-' . \Illuminate\Support\Str::uuid()->toString(),
            'message_text' => 'Test message',
            'payload' => ['test' => true],
            'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.support.replay-webhook', $business))
            ->assertRedirect(route('admin.support.show', $business));
    }

    public function test_super_admin_can_rerun_billing_cycle(): void
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

        $this->actingAs($admin)
            ->post(route('admin.support.rerun-billing', $business), [
                'confirm_rerun' => '1',
                'note' => 'Test rerun',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'business_id' => $business->id,
            'action' => 'billing.rerun_cycle',
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
            ->post(route('admin.support.refresh-launch', $business))
            ->assertRedirect();
    }

    public function test_super_admin_can_export_audit_log(): void
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

        // Create a support note first so there's audit data to export
        // Create an audit log directly for testing export
        app(AuditLogger::class)->log(
            actor: $admin,
            action: 'support.note_private_added',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: ['note_preview' => 'Test export data', 'is_private' => true],
            businessId: $business->id,
        );

        $response = $this->actingAs($admin)->get(route('admin.support.export-audit'));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
    }

    public function test_non_super_admin_cannot_access_support_workspace(): void
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

        $this->actingAs($owner)->get(route('admin.support.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.support.show', $business))->assertForbidden();
        $this->actingAs($owner)->post(route('admin.support.add-note', $business))->assertForbidden();
        $this->actingAs($owner)->post(route('admin.support.replay-webhook', $business))->assertForbidden();
    }
}
