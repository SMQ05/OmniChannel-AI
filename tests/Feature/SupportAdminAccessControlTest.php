<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\IncidentBanner;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportAdminAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_support_admin_is_denied_control_plane_pages(): void
    {
        $supportAdmin = User::query()->create([
            'business_id' => null,
            'name' => 'Support Admin',
            'email' => 'support-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'support_admin',
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

        $this->actingAs($supportAdmin)->get(route('admin.onboarding.index'))->assertForbidden();
        $this->actingAs($supportAdmin)->get(route('admin.credentials.index'))->assertForbidden();
        $this->actingAs($supportAdmin)->get(route('admin.ai-policy.index'))->assertForbidden();
        $this->actingAs($supportAdmin)->get(route('admin.support.index'))->assertForbidden();
        $this->actingAs($supportAdmin)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($supportAdmin)->get(route('admin.incidents.index'))->assertForbidden();
        $this->actingAs($supportAdmin)->get(route('admin.monitoring.index'))->assertForbidden();
        $this->actingAs($supportAdmin)->get(route('admin.compliance.index'))->assertForbidden();
        $this->actingAs($supportAdmin)->get(route('admin.onboarding.show', $business))->assertForbidden();
    }

    public function test_support_admin_cannot_execute_privileged_incident_action(): void
    {
        $supportAdmin = User::query()->create([
            'business_id' => null,
            'name' => 'Support Admin',
            'email' => 'support-admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'support_admin',
        ]);

        $incident = IncidentBanner::query()->create([
            'title' => 'Maintenance Notice',
            'message' => 'Scheduled maintenance.',
            'severity' => 'info',
            'is_platform_wide' => true,
            'status' => 'draft',
        ]);

        $this->actingAs($supportAdmin)
            ->post(route('admin.incidents.publish', $incident), ['publish_now' => '1'])
            ->assertForbidden();
    }
}
