<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\IncidentBanner;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_audit_logs_index(): void
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

        // Create an audit log entry
        app(AuditLogger::class)->log(
            actor: null,
            action: 'test action',
            subjectType: Business::class,
            subjectId: $business->id,
            businessId: $business->id,
        );

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.index'));
        $response->assertStatus(200);
        $response->assertSee('test action');
        $response->assertSee('Clinic');
    }

    public function test_super_admin_can_view_single_audit_log_entry(): void
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

        $auditLog = app(AuditLogger::class)->log(
            actor: null,
            action: 'launch.approved',
            subjectType: Business::class,
            subjectId: $business->id,
            businessId: $business->id,
        );

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.show', $auditLog));
        $response->assertStatus(200);
        $response->assertSee('launch.approved');
        $response->assertSee('Clinic');
    }

    public function test_super_admin_can_search_audit_logs_by_request_id(): void
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

        $requestId = \Illuminate\Support\Str::uuid()->toString();
        app(AuditLogger::class)->log(
            actor: null,
            action: 'test action',
            subjectType: Business::class,
            subjectId: $business->id,
            payload: ['request_id' => $requestId],
            businessId: $business->id,
        );

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.search-request-id', ['request_id' => $requestId]));
        $response->assertStatus(200);
        $response->assertJsonFragment(['action' => 'test action']);
    }

    public function test_super_admin_can_export_audit_logs(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.export'));
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=utf-8');
    }

    public function test_super_admin_can_view_audit_logs_for_business(): void
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

        app(AuditLogger::class)->log(
            actor: null,
            action: 'launch.approved',
            subjectType: Business::class,
            subjectId: $business->id,
            businessId: $business->id,
        );
        app(AuditLogger::class)->log(
            actor: null,
            action: 'billing.cycle_run',
            subjectType: Business::class,
            subjectId: $business->id,
            businessId: $business->id,
        );

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.for-business', $business));
        $response->assertStatus(200);
        $response->assertSee('launch.approved');
        $response->assertSee('billing.cycle_run');
    }

    public function test_super_admin_can_view_recent_audit_logs(): void
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

        app(AuditLogger::class)->log(
            actor: null,
            action: 'test action 1',
            subjectType: Business::class,
            subjectId: $business->id,
            businessId: $business->id,
        );
        app(AuditLogger::class)->log(
            actor: null,
            action: 'test action 2',
            subjectType: Business::class,
            subjectId: $business->id,
            businessId: $business->id,
        );

        $response = $this->actingAs($admin)->get(route('admin.audit-logs.recent'));
        $response->assertStatus(200);
        $response->assertSee('test action');
    }

    public function test_audit_log_captures_admin_actions(): void
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

        // Perform an action that should be logged
        $this->actingAs($admin)
            ->post(route('admin.incidents.publish', IncidentBanner::query()->create([
                'title' => 'Test',
                'message' => 'Test message',
                'severity' => 'info',
                'is_platform_wide' => true,
                'status' => 'draft',
            ])), [
                'publish_now' => '1',
            ])
            ->assertRedirect(route('admin.incidents.index'));

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'incident.published',
            'business_id' => null,
        ]);
    }

    public function test_non_super_admin_cannot_access_audit_logs(): void
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

        $this->actingAs($owner)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.audit-logs.export'))->assertForbidden();
    }
}
