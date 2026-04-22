<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Business;
use App\Models\MonitoringSnapshot;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMonitoringControlPlaneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_view_monitoring_page_and_capture_snapshots(): void
    {
        $admin = User::query()->create([
            'business_id' => null,
            'name' => 'Admin',
            'email' => 'admin-monitoring@example.com',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $business = Business::query()->create([
            'name' => 'Snapshot Clinic',
            'business_type' => 'clinic',
            'slug' => 'snapshot-clinic',
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
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertSee('Derived control-plane snapshots only');

        $this->actingAs($admin)
            ->post(route('admin.monitoring.capture-platform'))
            ->assertRedirect(route('admin.monitoring.index'));

        $this->actingAs($admin)
            ->post(route('admin.monitoring.capture-tenant', $business))
            ->assertRedirect(route('admin.monitoring.index'));

        $this->assertDatabaseHas('monitoring_snapshots', [
            'scope' => 'platform',
            'snapshot_type' => 'platform_health',
        ]);
        $this->assertDatabaseHas('monitoring_snapshots', [
            'scope' => 'tenant',
            'business_id' => $business->id,
            'snapshot_type' => 'tenant_operability',
        ]);

        $platformSnapshot = MonitoringSnapshot::query()
            ->where('scope', 'platform')
            ->latest('captured_at')
            ->first();

        $this->assertTrue((bool) ($platformSnapshot?->aggregates['derived_only'] ?? false));
    }
}
