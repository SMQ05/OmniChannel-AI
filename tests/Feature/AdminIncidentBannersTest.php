<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\IncidentBanner;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminIncidentBannersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_incident_banners_index(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        IncidentBanner::query()->create([
            'title' => 'Maintenance Notice',
            'message' => 'Scheduled maintenance on Saturday at 2AM UTC.',
            'severity' => 'info',
            'is_platform_wide' => true,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.incidents.index'));
        $response->assertStatus(200);
        $response->assertSee('Maintenance Notice');
        $response->assertSee('Draft');
    }

    public function test_super_admin_can_create_incident_banner(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.incidents.create'));
        $response->assertStatus(200);
        $response->assertSee('Create Incident Banner');
    }

    public function test_super_admin_can_store_incident_banner(): void
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
            ->post(route('admin.incidents.store'), [
                'title' => 'Planned Maintenance',
                'message' => 'We will perform scheduled maintenance on Saturday.',
                'severity' => 'warning',
                'is_platform_wide' => '0',
                'business_id' => $business->id,
                'starts_at' => now()->addDay()->toDateTimeString(),
                'ends_at' => now()->addDays(2)->toDateTimeString(),
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.incidents.index'));

        $this->assertDatabaseHas('incident_banners', [
            'title' => 'Planned Maintenance',
            'message' => 'We will perform scheduled maintenance on Saturday.',
            'severity' => 'warning',
            'is_platform_wide' => false,
            'business_id' => $business->id,
            'status' => 'draft',
        ]);
    }

    public function test_super_admin_can_create_platform_wide_incident(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.incidents.store'), [
                'title' => 'Global Outage',
                'message' => 'We are experiencing a global outage.',
                'severity' => 'critical',
                'is_platform_wide' => '1',
                'status' => 'draft',
            ])
            ->assertRedirect(route('admin.incidents.index'));

        $this->assertDatabaseHas('incident_banners', [
            'title' => 'Global Outage',
            'is_platform_wide' => true,
            'business_id' => null,
        ]);
    }

    public function test_super_admin_can_edit_incident_banner(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $incident = IncidentBanner::query()->create([
            'title' => 'Maintenance Notice',
            'message' => 'Scheduled maintenance.',
            'severity' => 'info',
            'is_platform_wide' => true,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.incidents.edit', $incident));
        $response->assertStatus(200);
        $response->assertSee('Maintenance Notice');
    }

    public function test_super_admin_can_update_incident_banner(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $incident = IncidentBanner::query()->create([
            'title' => 'Maintenance Notice',
            'message' => 'Scheduled maintenance.',
            'severity' => 'info',
            'is_platform_wide' => true,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.incidents.update', $incident), [
                'title' => 'Updated Maintenance Notice',
                'message' => 'Updated maintenance message.',
                'severity' => 'warning',
                'is_platform_wide' => '1',
            ])
            ->assertRedirect(route('admin.incidents.index'));

        $this->assertDatabaseHas('incident_banners', [
            'id' => $incident->id,
            'title' => 'Updated Maintenance Notice',
            'message' => 'Updated maintenance message.',
            'severity' => 'warning',
        ]);
    }

    public function test_super_admin_can_publish_incident(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $incident = IncidentBanner::query()->create([
            'title' => 'Maintenance Notice',
            'message' => 'Scheduled maintenance.',
            'severity' => 'info',
            'is_platform_wide' => true,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.incidents.publish', $incident), [
                'publish_now' => '1',
            ])
            ->assertRedirect(route('admin.incidents.index'));

        $this->assertDatabaseHas('incident_banners', [
            'id' => $incident->id,
            'status' => 'published',
        ]);
        $this->assertNotNull($incident->fresh()->published_at);

        // Audit log should be created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'incident.published',
        ]);
    }

    public function test_super_admin_can_archive_incident(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $incident = IncidentBanner::query()->create([
            'title' => 'Maintenance Notice',
            'message' => 'Scheduled maintenance.',
            'severity' => 'info',
            'is_platform_wide' => true,
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.incidents.archive', $incident), [
                'reason' => 'Scheduled maintenance completed.',
            ])
            ->assertRedirect(route('admin.incidents.index'));

        $this->assertDatabaseHas('incident_banners', [
            'id' => $incident->id,
            'status' => 'archived',
        ]);
    }

    public function test_super_admin_can_resolve_incident(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $incident = IncidentBanner::query()->create([
            'title' => 'Service Disruption',
            'message' => 'We are experiencing service issues.',
            'severity' => 'critical',
            'is_platform_wide' => true,
            'status' => 'published',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.incidents.resolve', $incident), [
                'resolution_message' => 'Issue has been fixed and services are back online.',
            ])
            ->assertRedirect(route('admin.incidents.index'));

        $this->assertDatabaseHas('incident_banners', [
            'id' => $incident->id,
            'status' => 'resolved',
        ]);

        // Audit log should be created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'incident.resolved',
        ]);
    }

    public function test_super_admin_can_delete_incident(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $incident = IncidentBanner::query()->create([
            'title' => 'Maintenance Notice',
            'message' => 'Scheduled maintenance.',
            'severity' => 'info',
            'is_platform_wide' => true,
            'status' => 'draft',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.incidents.destroy', $incident), [
                'confirm_delete' => '1',
            ])
            ->assertRedirect(route('admin.incidents.index'));

        $this->assertDatabaseMissing('incident_banners', ['id' => $incident->id]);
    }

    public function test_super_admin_can_view_active_business_incidents(): void
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

        IncidentBanner::query()->create([
            'title' => 'Maintenance Notice',
            'message' => 'Scheduled maintenance.',
            'severity' => 'info',
            'is_platform_wide' => false,
            'business_id' => $business->id,
            'status' => 'published',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.incidents.active-for-business', $business));
        $response->assertStatus(200);
        $response->assertSee('Maintenance Notice');
    }

    public function test_non_super_admin_cannot_access_incident_banners(): void
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

        IncidentBanner::query()->create([
            'title' => 'Maintenance Notice',
            'message' => 'Scheduled maintenance.',
            'severity' => 'info',
            'is_platform_wide' => true,
            'status' => 'draft',
        ]);

        $owner = User::query()->create([
            'business_id' => $business->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'role' => 'business_owner',
        ]);

        $this->actingAs($owner)->get(route('admin.incidents.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.incidents.create'))->assertForbidden();
        $this->actingAs($owner)->post(route('admin.incidents.store'))->assertForbidden();
    }
}
